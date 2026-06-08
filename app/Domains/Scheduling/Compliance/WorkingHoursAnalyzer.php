<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Data\ComplianceFinding;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wertet Dienstzuweisungen gegen die aktiven, editierbaren ArbZG-Regeln (`compliance_rules`) aus und liefert
 * strukturierte Befunde je Mitarbeiter:in. Nachtdienste über Mitternacht werden korrekt als zusammenhängendes
 * Intervall behandelt. Schwellwerte stammen aus der Regel — Editieren wirkt sofort.
 */
class WorkingHoursAnalyzer
{
    /**
     * @param  Collection<int, ShiftAssignment>  $assignments  mit geladenen user + shift Relationen
     * @param  Collection<int, ComplianceRule>  $rules
     * @return array<int, ComplianceFinding>
     */
    public function analyze(Collection $assignments, Collection $rules): array
    {
        $active = $rules->where('aktiv', true)->keyBy('key');
        if ($active->isEmpty()) {
            return [];
        }

        $findings = [];
        foreach ($assignments->groupBy('user_id') as $userId => $userAssignments) {
            $userName = $userAssignments->first()?->user->name ?? '#'.$userId;
            $intervals = $userAssignments
                ->filter(fn (ShiftAssignment $a) => $a->shift !== null)
                ->map(fn (ShiftAssignment $a) => $this->interval($a))
                ->sortBy('start')->values()->all();

            array_push($findings, ...$this->forUser($active, (int) $userId, $userName, $intervals));
        }

        return $findings;
    }

    /**
     * @param  Collection<string, ComplianceRule>  $rules
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable, hours: float, date: string}>  $intervals
     * @return array<int, ComplianceFinding>
     */
    private function forUser(Collection $rules, int $userId, string $userName, array $intervals): array
    {
        $out = [];
        // WHY: echte Closure mit &$out — eine Arrow-fn (fn) würde $out by value capturen und das Ergebnis verwerfen.
        $make = function (ComplianceRule $rule, ViolationSeverity $sev, string $msg, array $dates) use (&$out, $userId, $userName) {
            $out[] = new ComplianceFinding(
                ruleKey: $rule->key, paragraph: $rule->paragraph, severity: $sev, label: $rule->label,
                message: $msg, userId: $userId, userName: $userName, dates: $dates, gesetzUrl: $rule->gesetz_url,
            );
        };

        // § 3 — Tägliche Höchstarbeitszeit
        if ($rule = $rules->get('tageshoechstarbeitszeit')) {
            $max = $rule->param('max_stunden', 10);
            $hinweisAb = $rule->param('hinweis_ab_stunden', 8);
            foreach ($intervals as $iv) {
                if ($iv['hours'] > $max) {
                    $make($rule, $rule->severity, "{$iv['date']}: {$iv['hours']} h überschreiten die Höchstarbeitszeit von {$max} h.", [$iv['date']]);
                } elseif ($iv['hours'] > $hinweisAb) {
                    $make($rule, ViolationSeverity::Hinweis, "{$iv['date']}: {$iv['hours']} h über {$hinweisAb} h — nur mit Ausgleich auf 8 h-Schnitt zulässig.", [$iv['date']]);
                }
            }
        }

        // § 5 — Ununterbrochene Ruhezeit zwischen zwei Diensten
        if ($rule = $rules->get('ruhezeit')) {
            $min = $rule->param('min_stunden', 11);
            $pflege = $rule->param('ausnahme_pflege_stunden', 10);
            for ($i = 0, $n = count($intervals) - 1; $i < $n; $i++) {
                $rest = round($intervals[$i]['end']->diffInMinutes($intervals[$i + 1]['start'], false) / 60, 2);
                $spanne = $intervals[$i]['date'].' → '.$intervals[$i + 1]['date'];
                if ($rest < $pflege) {
                    $make($rule, $rule->severity, "Ruhezeit {$rest} h ({$spanne}) unter der Mindestruhezeit (auch die Pflege-Ausnahme erlaubt nur {$pflege} h).", [$intervals[$i + 1]['date']]);
                } elseif ($rest < $min) {
                    $make($rule, ViolationSeverity::Warnung, "Ruhezeit {$rest} h ({$spanne}) unter {$min} h — Pflege-Ausnahme (§ 5 Abs. 2) nur mit Ausgleich im selben Monat.", [$intervals[$i + 1]['date']]);
                }
            }
        }

        // § 3 — Wöchentliche Höchstarbeitszeit (Durchschnittsgrenze)
        if ($rule = $rules->get('wochenarbeitszeit')) {
            $maxWeek = $rule->param('max_stunden_woche', 48);
            $byWeek = [];
            foreach ($intervals as $iv) {
                $wk = $iv['start']->isoFormat('GGGG-[KW]WW');
                $byWeek[$wk] = ($byWeek[$wk] ?? 0) + $iv['hours'];
            }
            foreach ($byWeek as $wk => $sum) {
                if ($sum > $maxWeek) {
                    $make($rule, $rule->severity, "{$wk}: ".round($sum, 1)." h über {$maxWeek} h — maßgeblich ist der 24-Wochen-Schnitt.", [$wk]);
                }
            }
        }

        // §§ 9–11 — Sonntagsbeschäftigung (in der Pflege zulässig, mit Auflagen)
        if ($rule = $rules->get('sonntagsruhe')) {
            foreach ($intervals as $iv) {
                if ($iv['start']->isSunday()) {
                    $make($rule, $rule->severity, "{$iv['date']}: Sonntagsdienst — in der Pflege zulässig (§ 10), Ersatzruhetag nach § 11 erforderlich.", [$iv['date']]);
                }
            }
        }

        // § 4 — Ruhepausen: im PLAN nicht hinterlegt, aber auf der Ist-Zeit prüfbar (die Pause ist in der
        // Zeiterfassung erfasst → Zeitbuchung::pausenStatus). Hier nur Hinweis + Verweis, kein totes „n/a".
        if ($rule = $rules->get('ruhepausen')) {
            $ab = $rule->param('pause_30_ab_stunden', 6);
            $lang = array_values(array_unique(array_map(fn ($iv) => $iv['date'], array_filter($intervals, fn ($iv) => $iv['hours'] > $ab))));
            if ($lang !== []) {
                $make($rule, ViolationSeverity::Hinweis, count($lang).' Dienst(e) über '.$ab.' h — § 4 wird in der Zeiterfassung gegen die erfassten Pausen geprüft.', $lang);
            }
        }

        return $out;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, hours: float, date: string}
     */
    private function interval(ShiftAssignment $a): array
    {
        $date = Carbon::parse($a->dienst_am)->toDateString();
        $start = CarbonImmutable::parse($date.' '.$a->shift->beginn);
        $end = CarbonImmutable::parse($date.' '.$a->shift->ende);
        if ($end <= $start) {
            $end = $end->addDay(); // Nachtdienst über Mitternacht
        }

        return ['start' => $start, 'end' => $end, 'hours' => self::stunden($a->shift->beginn, $a->shift->ende), 'date' => $date];
    }

    /** Dauer einer Schicht in Stunden — Nachtdienste über Mitternacht werden korrekt gezählt. */
    public static function stunden(string $beginn, string $ende): float
    {
        $start = CarbonImmutable::parse('2000-01-01 '.$beginn);
        $end = CarbonImmutable::parse('2000-01-01 '.$ende);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }
}
