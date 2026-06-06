<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Data\SpitzenzeitAnalyse;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\Spitzenzeit;
use Carbon\CarbonImmutable;

/**
 * Wertet die geplanten Schichten einer Woche gegen die Bedarfs-Fenster (Spitzenzeiten) aus: je Fenster × Tag
 * wie viele Mitarbeitende mit überlappender Schicht anwesend sind (Ist) vs. das Soll. Daraus Ampel und konkrete
 * Vorschläge, wo zusätzliche kurze Spitzendienste den Deckungsgrad zur Spitzenzeit verbessern würden.
 */
class SpitzenzeitAnalyzer
{
    public function analysiere(int $tenantId, string $weekStart): SpitzenzeitAnalyse
    {
        $fenster = SpitzenzeitDefaults::ensureFor($tenantId)->where('aktiv', true)->values();

        $start = CarbonImmutable::parse($weekStart);
        $tage = [];
        foreach (range(0, 6) as $i) {
            $d = $start->addDays($i);
            $tage[] = ['datum' => $d->toDateString(), 'kurz' => $d->isoFormat('dd'), 'tag' => $d->isoFormat('DD.MM.'), 'wochenende' => $d->isoWeekday() >= 6];
        }
        $von = $tage[0]['datum'];
        $bis = $tage[6]['datum'];

        // Anwesende Mitarbeitende je Tag (über die zugewiesene Schicht-Zeitspanne).
        $assignments = ShiftAssignment::with('shift')->whereBetween('dienst_am', [$von, $bis])->get();
        $proTag = [];
        foreach ($assignments as $a) {
            if ($a->shift === null) {
                continue;
            }
            $datum = CarbonImmutable::parse($a->dienst_am)->toDateString();
            $proTag[$datum][] = ['user_id' => $a->user_id, 'beginn' => (string) $a->shift->beginn, 'ende' => (string) $a->shift->ende];
        }

        $zellen = [];
        $vorschlaege = [];
        foreach ($fenster as $f) {
            foreach ($tage as $t) {
                $aktiv = ! ($f->nur_werktags && $t['wochenende']);
                $ist = 0;
                if ($aktiv) {
                    $userIds = [];
                    foreach ($proTag[$t['datum']] ?? [] as $eintrag) {
                        if ($f->wirdGedecktVon($eintrag['beginn'], $eintrag['ende'])) {
                            $userIds[$eintrag['user_id']] = true;
                        }
                    }
                    $ist = count($userIds);
                }
                $ampel = $aktiv ? self::ampel($ist, $f->soll_personen) : 'gruen';
                $zellen[$f->id][$t['datum']] = ['ist' => $ist, 'soll' => $f->soll_personen, 'ampel' => $ampel, 'aktiv' => $aktiv];

                if ($aktiv && $ist < $f->soll_personen) {
                    $fehlt = $f->soll_personen - $ist;
                    $vorschlaege[] = "{$t['kurz']} {$t['tag']}: +{$fehlt} Spitzendienst »{$f->name}« ({$f->beginn}–{$f->ende}) — Ist {$ist}/{$f->soll_personen}.";
                }
            }
        }

        return new SpitzenzeitAnalyse($fenster, $tage, $zellen, $vorschlaege);
    }

    public static function ampel(int $ist, int $soll): string
    {
        $fehlt = $soll - $ist;

        return $fehlt <= 0 ? 'gruen' : ($fehlt === 1 ? 'gelb' : 'rot');
    }
}
