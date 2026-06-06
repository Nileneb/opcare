<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Data\QualityFinding;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ScheduleQualityRule;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Wertet die aktiven ergonomischen Schichtregeln gegen den Wochenplan aus — pro Mitarbeiter:in eine
 * Tages-Timeline (Datum → Schichten). Liefert Empfehlungs-Befunde (Warnung/Hinweis), die der harten
 * ArbZG-Prüfung nachgelagert sind. Jede Timeline-Zelle hält Art + Beginn/Ende für die Ruhezeit-Rechnung.
 */
class ScheduleQualityAnalyzer
{
    private const RANG = ['frueh' => 1, 'zwischendienst' => 2, 'spaet' => 3, 'nacht' => 4];

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<int, ScheduleQualityRule>  $rules
     * @param  array<int, string>  $dates  aufsteigend sortierte Tage der Woche
     * @return array<int, QualityFinding>
     */
    public function findings(Collection $assignments, Collection $rules, array $dates): array
    {
        $aktive = $rules->where('aktiv', true);
        if ($aktive->isEmpty()) {
            return [];
        }

        // Timeline je Mitarbeiter:in: datum => [ ['kind'=>ShiftKind,'beginn'=>'HH:MM','ende'=>'HH:MM'], … ]
        $timelines = [];
        $namen = [];
        foreach ($assignments as $a) {
            if ($a->shift === null) {
                continue;
            }
            $timelines[$a->user_id][$a->dienst_am->toDateString()][] = [
                'kind' => $a->shift->kind, 'beginn' => (string) $a->shift->beginn, 'ende' => (string) $a->shift->ende,
            ];
            $namen[$a->user_id] = $a->user->name;
        }

        $out = [];
        foreach ($timelines as $userId => $perDay) {
            foreach ($aktive as $rule) {
                $msg = $this->pruefe($rule, $perDay, $dates);
                if ($msg !== null) {
                    $out[] = new QualityFinding($userId, $namen[$userId], $rule->key, $rule->severity, $rule->label, $msg, $rule->quelle);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, array{kind: ShiftKind, beginn: string, ende: string}>>  $perDay
     * @param  array<int, string>  $dates
     */
    private function pruefe(ScheduleQualityRule $rule, array $perDay, array $dates): ?string
    {
        return match ($rule->key) {
            'max-folge-arbeitstage' => $this->maxFolge($perDay, $dates, fn ($cells) => $cells !== [], (int) $rule->param('max_tage', 7),
                fn ($n) => "{$n} Arbeitstage am Stück (empfohlen ≤ ".(int) $rule->param('max_tage', 7).')'),
            'max-folge-nachtdienste' => $this->maxFolge($perDay, $dates, fn ($cells) => $this->hat($cells, ShiftKind::Nacht), (int) $rule->param('max_naechte', 3),
                fn ($n) => "{$n} Nachtdienste am Stück (empfohlen ≤ ".(int) $rule->param('max_naechte', 3).')'),
            'min-freiblock' => $this->minFreiblock($perDay, $dates, (int) $rule->param('min_tage', 2)),
            'quick-return' => $this->quickReturn($perDay, $dates, (float) $rule->param('min_ruhe_stunden', 16)),
            'vorwaerts-rotation' => $this->vorwaerts($perDay, $dates),
            default => null,
        };
    }

    /**
     * @param  array<string, array<int, array{kind: ShiftKind, beginn: string, ende: string}>>  $perDay
     * @param  array<int, string>  $dates
     */
    private function maxFolge(array $perDay, array $dates, callable $trifft, int $max, callable $msg): ?string
    {
        $run = 0;
        $best = 0;
        foreach ($dates as $d) {
            $run = $trifft($perDay[$d] ?? []) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return $best > $max ? $msg($best) : null;
    }

    /**
     * @param  array<string, array<int, array{kind: ShiftKind, beginn: string, ende: string}>>  $perDay
     * @param  array<int, string>  $dates
     */
    private function minFreiblock(array $perDay, array $dates, int $min): ?string
    {
        $run = 0;
        $best = 0;
        foreach ($dates as $d) {
            $run = empty($perDay[$d] ?? []) ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return $best < $min ? "kein zusammenhängender Freiblock von ≥ {$min} Tagen in der Woche" : null;
    }

    /**
     * @param  array<string, array<int, array{kind: ShiftKind, beginn: string, ende: string}>>  $perDay
     * @param  array<int, string>  $dates
     */
    private function quickReturn(array $perDay, array $dates, float $minRuhe): ?string
    {
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $heute = $perDay[$dates[$i]] ?? [];
            $morgen = $perDay[$dates[$i + 1]] ?? [];
            $spaet = $this->zelle($heute, ShiftKind::Spaet);
            $frueh = $this->zelle($morgen, ShiftKind::Frueh);
            if ($spaet === null || $frueh === null) {
                continue;
            }
            $ruhe = $this->ruheStunden($spaet['ende'], $frueh['beginn']);
            if ($ruhe < $minRuhe) {
                return 'Spät→Früh am '.CarbonImmutable::parse($dates[$i])->isoFormat('dd DD.MM.')
                    .' → nur '.number_format($ruhe, 1, ',', '.').' h Ruhe (empfohlen ≥ '.number_format($minRuhe, 0).' h)';
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array{kind: ShiftKind, beginn: string, ende: string}>>  $perDay
     * @param  array<int, string>  $dates
     */
    private function vorwaerts(array $perDay, array $dates): ?string
    {
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $heute = $perDay[$dates[$i]] ?? [];
            $morgen = $perDay[$dates[$i + 1]] ?? [];
            if ($heute === [] || $morgen === []) {
                continue;
            }
            $rangHeute = max(array_map(fn ($c) => self::RANG[$c['kind']->value] ?? 0, $heute));
            $rangMorgen = max(array_map(fn ($c) => self::RANG[$c['kind']->value] ?? 0, $morgen));
            if ($rangMorgen < $rangHeute) {
                return 'Rückwärtsrotation am '.CarbonImmutable::parse($dates[$i + 1])->isoFormat('dd DD.MM.').' (höhere → niedrigere Schicht)';
            }
        }

        return null;
    }

    /** Ruhe Spät→Früh in Stunden: (24:00 − Spätende) + Frühbeginn. */
    private function ruheStunden(string $ende, string $beginn): float
    {
        return round((1440 - $this->minuten($ende) + $this->minuten($beginn)) / 60, 1);
    }

    private function minuten(string $zeit): int
    {
        [$h, $m] = array_pad(explode(':', $zeit), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }

    /**
     * @param  array<int, array{kind: ShiftKind, beginn: string, ende: string}>  $cells
     */
    private function hat(array $cells, ShiftKind $kind): bool
    {
        return $this->zelle($cells, $kind) !== null;
    }

    /**
     * @param  array<int, array{kind: ShiftKind, beginn: string, ende: string}>  $cells
     * @return array{kind: ShiftKind, beginn: string, ende: string}|null
     */
    private function zelle(array $cells, ShiftKind $kind): ?array
    {
        foreach ($cells as $c) {
            if ($c['kind'] === $kind) {
                return $c;
            }
        }

        return null;
    }
}
