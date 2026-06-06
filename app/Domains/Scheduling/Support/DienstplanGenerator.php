<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;
use App\Domains\Scheduling\Compliance\ScheduleQualityDefaults;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\Abwesenheit;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Greedy-Constraint-Generator für einen Wochen-Dienstplan. Eingaben sind die bereits vorhandenen Bausteine:
 * Soll-Besetzung je Schicht, harte ArbZG-Grenzen (Ruhezeit/Wochenstunden), ergonomische Empfehlungen (Scoring),
 * der Wunschdienstplan (Verfügbarkeit) und das Vertrags-Pensum (Fairness). Erzeugt Vorschläge (auto_generiert),
 * die die PDL nur prüft und freigibt. Bestehende manuelle Zuweisungen bleiben unangetastet; offene Slots
 * werden transparent gemeldet (keine stille Unterdeckung).
 */
class DienstplanGenerator
{
    private const RANG = ['frueh' => 1, 'zwischendienst' => 2, 'spaet' => 3, 'nacht' => 4];

    public function generate(int $tenantId, string $weekStart): GenerationResult
    {
        $start = CarbonImmutable::parse($weekStart)->startOfDay();
        $dates = collect(range(0, 6))->map(fn ($i) => $start->addDays($i)->toDateString())->all();
        $von = $dates[0];
        // Exklusive Obergrenze (nächster Montag): dienst_am wird als Datetime gespeichert, ein
        // whereBetween mit Datum-Strings würde den Sonntag (…00:00:00 > '…') verfehlen.
        $bisExklusiv = $start->addDays(7)->toDateString();

        $shifts = Shift::where('tenant_id', $tenantId)->where('aktiv', true)->orderBy('beginn')->get();
        $users = User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->with('employeeProfile')->get();

        $arbzg = ArbeitszeitgesetzDefaults::ensureFor($tenantId);
        $minRuhe = (float) ($arbzg->firstWhere('key', 'ruhezeit')?->param('min_stunden', 11) ?? 11);
        $maxWoche = (float) ($arbzg->firstWhere('key', 'wochenarbeitszeit')?->param('max_stunden_woche', 48)
            ?? $arbzg->firstWhere('key', 'wochenhoechstarbeitszeit')?->param('max_stunden_woche', 48) ?? 48);

        $qrules = ScheduleQualityDefaults::ensureFor($tenantId)->where('aktiv', true)->keyBy('key');
        $maxFolgeTage = (int) ($qrules->get('max-folge-arbeitstage')?->param('max_tage', 7) ?? 7);
        $maxFolgeNacht = (int) ($qrules->get('max-folge-nachtdienste')?->param('max_naechte', 3) ?? 3);
        $quickRuhe = (float) ($qrules->get('quick-return')?->param('min_ruhe_stunden', 16) ?? 16);

        // Mindest-Fachkraftquote je Schicht (§ 113c/Landesheimrecht) — hart erzwungen.
        $fachkraftquote = PersonalbemessungDefaults::ensureConfig($tenantId)->fachkraftquote_min;

        // Vorhandene manuelle Zuweisungen zählen als Belegung; alte Vorschläge dieser Woche werden ersetzt.
        ShiftAssignment::where('tenant_id', $tenantId)->where('dienst_am', '>=', $von)->where('dienst_am', '<', $bisExklusiv)->where('auto_generiert', true)->delete();
        $existing = ShiftAssignment::with('shift')->where('tenant_id', $tenantId)->where('dienst_am', '>=', $von)->where('dienst_am', '<', $bisExklusiv)->get();

        // Wünsche: user_id => datum => WunschTyp
        $wuensche = [];
        foreach (Dienstwunsch::where('tenant_id', $tenantId)->where('datum', '>=', $von)->where('datum', '<', $bisExklusiv)->get() as $w) {
            $wuensche[$w->user_id][$w->datum->toDateString()] = $w->typ;
        }

        // Abwesenheiten (Krank/Urlaub): an gedeckten Tagen ist die Person nicht planbar.
        $abwesend = [];
        $bisLetzter = $dates[6];
        foreach (Abwesenheit::where('tenant_id', $tenantId)->where('von', '<=', $bisLetzter)->where('bis', '>=', $von)->get() as $abw) {
            foreach ($dates as $datum) {
                if ($abw->deckt($datum)) {
                    $abwesend[$abw->user_id][$datum] = true;
                }
            }
        }

        // Mitarbeiter-Status
        $state = [];
        foreach ($users as $u) {
            $state[$u->id] = [
                'user' => $u,
                // employeeProfile ist durch whereHas() garantiert vorhanden.
                'fachkraft' => $u->employeeProfile->qualifikation?->istFachkraft() ?? false,
                'contract' => (float) ($u->employeeProfile->wochenstunden ?? 38.5),
                'hours' => 0.0,
                'tage' => [],          // datum => Shift
                'wochenenden' => 0,
            ];
        }
        foreach ($existing as $a) {
            if ($a->shift && isset($state[$a->user_id])) {
                $d = $a->dienst_am->toDateString();
                $state[$a->user_id]['tage'][$d] = $a->shift;
                $state[$a->user_id]['hours'] += WorkingHoursAnalyzer::stunden($a->shift->beginn, $a->shift->ende);
                if ($this->istWochenende($d)) {
                    $state[$a->user_id]['wochenenden']++;
                }
            }
        }

        // Bedarfs-Slots: je Tag × Schicht × Soll-Besetzung. Schwer-zu-besetzen zuerst (Nacht, Wochenende).
        // Je (Tag,Schicht) wird die geforderte Fachkraftzahl und der bereits belegte Stand mitgeführt, damit
        // die Mindest-Fachkraftquote hart eingehalten wird.
        $slots = [];
        $shiftInfo = [];
        foreach ($dates as $d) {
            foreach ($shifts as $shift) {
                $key = $d.'|'.$shift->id;
                $belegt = collect($existing)->filter(fn ($a) => $a->dienst_am->toDateString() === $d && $a->shift_id === $shift->id);
                $shiftInfo[$key] = [
                    'total' => (int) $shift->soll_besetzung,
                    // Quote per floor (keine „halbe Fachkraft" auf Einzelschichten erzwingen); Nachtdienst aber
                    // immer mind. 1 Fachkraft (Landesheimrecht).
                    'fk_req' => max(
                        (int) floor((int) $shift->soll_besetzung * $fachkraftquote),
                        $shift->kind === ShiftKind::Nacht ? 1 : 0,
                    ),
                    'filled' => $belegt->count(),
                    'fk' => $belegt->filter(fn ($a) => $state[$a->user_id]['fachkraft'] ?? false)->count(),
                ];
                for ($i = $belegt->count(); $i < (int) $shift->soll_besetzung; $i++) {
                    $slots[] = ['datum' => $d, 'shift' => $shift];
                }
            }
        }
        usort($slots, function ($a, $b) {
            $wa = ($this->istWochenende($a['datum']) ? 0 : 1);
            $wb = ($this->istWochenende($b['datum']) ? 0 : 1);
            if ($wa !== $wb) {
                return $wa <=> $wb;
            }
            $ra = self::RANG[$a['shift']->kind->value] ?? 9;
            $rb = self::RANG[$b['shift']->kind->value] ?? 9;

            return $rb <=> $ra; // Nacht (4) vor Früh (1)
        });

        $gefordert = count($slots) + collect($existing)->count();
        $created = [];
        $offen = [];

        foreach ($slots as $slot) {
            $d = $slot['datum'];
            $shift = $slot['shift'];
            $key = $d.'|'.$shift->id;
            $stunden = WorkingHoursAnalyzer::stunden($shift->beginn, $shift->ende);
            $fachkraftDa = $this->hatFachkraft($d, $shift->id, $existing, $created, $state);

            // Restplätze dieser Schicht und noch fehlende Fachkräfte → Fachkraft hart erzwingen,
            // wenn sonst die Quote nicht mehr erreichbar wäre.
            $info = $shiftInfo[$key];
            $restplaetze = $info['total'] - $info['filled'];
            $fkFehlt = $info['fk_req'] - $info['fk'];
            $nurFachkraft = $fkFehlt >= $restplaetze;

            $best = null;
            $bestScore = -INF;
            foreach ($state as $uid => $s) {
                if (isset($s['tage'][$d])) {
                    continue; // schon an diesem Tag eingeteilt
                }
                if ($abwesend[$uid][$d] ?? false) {
                    continue; // krank/Urlaub
                }
                if ($nurFachkraft && ! $s['fachkraft']) {
                    continue; // Mindest-Fachkraftquote der Schicht
                }
                $wunsch = $wuensche[$uid][$d] ?? null;
                if ($wunsch === WunschTyp::Frei || $wunsch === WunschTyp::NichtVerfuegbar) {
                    continue;
                }
                if ($maxWoche < $s['hours'] + $stunden) {
                    continue; // § 3 ArbZG Wochenhöchstarbeitszeit
                }
                if (! $this->ruheOk($d, $shift, $s['tage'], $minRuhe)) {
                    continue; // § 5 ArbZG Ruhezeit
                }

                $score = $this->score($d, $shift, $s, $wunsch, $fachkraftDa, $quickRuhe, $maxFolgeTage, $maxFolgeNacht, $uid);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $uid;
                }
            }

            if ($best === null) {
                $offen[] = CarbonImmutable::parse($d)->isoFormat('dd DD.MM.').' · '.$shift->name.($nurFachkraft ? ' (Fachkraft nötig)' : '');

                continue;
            }

            $created[] = ['datum' => $d, 'shift_id' => $shift->id, 'shift' => $shift, 'user_id' => $best];
            $shiftInfo[$key]['filled']++;
            if ($state[$best]['fachkraft']) {
                $shiftInfo[$key]['fk']++;
            }
            $state[$best]['tage'][$d] = $shift;
            $state[$best]['hours'] += $stunden;
            if ($this->istWochenende($d)) {
                $state[$best]['wochenenden']++;
            }
        }

        DB::transaction(function () use ($created, $tenantId) {
            foreach ($created as $c) {
                ShiftAssignment::create([
                    'tenant_id' => $tenantId, 'user_id' => $c['user_id'], 'shift_id' => $c['shift_id'],
                    'dienst_am' => $c['datum'], 'auto_generiert' => true,
                ]);
            }
        });

        return new GenerationResult(
            slots: $gefordert,
            besetzt: collect($existing)->count() + count($created),
            erstellt: count($created),
            offeneSlots: $offen,
        );
    }

    /**
     * @param  array<string, Shift>  $tage
     */
    private function ruheOk(string $d, Shift $shift, array $tage, float $minRuhe): bool
    {
        $vortag = CarbonImmutable::parse($d)->subDay()->toDateString();
        $folgetag = CarbonImmutable::parse($d)->addDay()->toDateString();

        if (isset($tage[$vortag]) && $this->ruheStunden($tage[$vortag], $vortag, $shift, $d) < $minRuhe) {
            return false;
        }
        if (isset($tage[$folgetag]) && $this->ruheStunden($shift, $d, $tage[$folgetag], $folgetag) < $minRuhe) {
            return false;
        }

        return true;
    }

    /** Ruhezeit zwischen dem Ende von Schicht A (an Tag a) und dem Beginn von Schicht B (an Tag b > a). */
    private function ruheStunden(Shift $a, string $aTag, Shift $b, string $bTag): float
    {
        $endeA = CarbonImmutable::parse($aTag.' '.$a->ende);
        if ($a->ende <= $a->beginn) {
            $endeA = $endeA->addDay(); // Nacht über Mitternacht
        }
        $beginnB = CarbonImmutable::parse($bTag.' '.$b->beginn);

        return $endeA->diffInMinutes($beginnB, false) / 60;
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function score(string $d, Shift $shift, array $s, ?WunschTyp $wunsch, bool $fachkraftDa, float $quickRuhe, int $maxFolgeTage, int $maxFolgeNacht, int $uid): float
    {
        $score = 0.0;
        if ($wunsch === WunschTyp::Arbeiten) {
            $score += 50;
        }
        $rest = $s['contract'] - $s['hours'];
        $score += $rest > 0 ? min($rest, 40) : -30;

        if ($s['fachkraft'] && ! $fachkraftDa) {
            $score += 25; // Fachkraft-Abdeckung je Schicht sicherstellen
        }

        // Ergonomie (weich): Vorwärtsrotation, Quick-Return, Folge-Tage/-Nächte, Wochenend-Gerechtigkeit.
        $vortag = CarbonImmutable::parse($d)->subDay()->toDateString();
        if (isset($s['tage'][$vortag])) {
            $rangV = self::RANG[$s['tage'][$vortag]->kind->value] ?? 0;
            $rangS = self::RANG[$shift->kind->value] ?? 0;
            if ($rangS < $rangV) {
                $score -= 10; // Rückwärtsrotation
            }
            if ($shift->kind === ShiftKind::Frueh && $s['tage'][$vortag]->kind === ShiftKind::Spaet
                && $this->ruheStunden($s['tage'][$vortag], $vortag, $shift, $d) < $quickRuhe) {
                $score -= 15; // Quick Return
            }
        }
        if ($this->folge($s['tage'], $d, fn ($sh) => true) >= $maxFolgeTage) {
            $score -= 20;
        }
        if ($shift->kind === ShiftKind::Nacht && $this->folge($s['tage'], $d, fn ($sh) => $sh->kind === ShiftKind::Nacht) >= $maxFolgeNacht) {
            $score -= 25;
        }
        if ($this->istWochenende($d)) {
            $score -= $s['wochenenden'] * 10;
        }

        return $score + ($uid % 3) * 0.1; // deterministischer Tie-Break
    }

    /**
     * Länge des laufenden Treffer-Blocks, der unmittelbar VOR Tag $d endet (für „würde-verlängern"-Prüfung).
     *
     * @param  array<string, Shift>  $tage
     */
    private function folge(array $tage, string $d, callable $trifft): int
    {
        $n = 0;
        $cursor = CarbonImmutable::parse($d)->subDay();
        while (isset($tage[$cursor->toDateString()]) && $trifft($tage[$cursor->toDateString()])) {
            $n++;
            $cursor = $cursor->subDay();
        }

        return $n + 1; // inkl. des zu setzenden Tages
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $existing
     * @param  array<int, array<string, mixed>>  $created
     * @param  array<int, array<string, mixed>>  $state
     */
    private function hatFachkraft(string $d, int $shiftId, $existing, array $created, array $state): bool
    {
        foreach ($existing as $a) {
            if ($a->dienst_am->toDateString() === $d && $a->shift_id === $shiftId && ($state[$a->user_id]['fachkraft'] ?? false)) {
                return true;
            }
        }
        foreach ($created as $c) {
            if ($c['datum'] === $d && $c['shift_id'] === $shiftId && ($state[$c['user_id']]['fachkraft'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function istWochenende(string $datum): bool
    {
        return CarbonImmutable::parse($datum)->isoWeekday() >= 6;
    }
}
