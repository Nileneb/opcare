<?php

namespace App\Domains\SocialCare\Services;

use App\Domains\SocialCare\Models\BetreuungsTeilnahme;

/**
 * Wertet die zusätzliche Betreuung (§ 43b SGB XI) aus: je Bewohner die Betreuungs-Einheiten + -Minuten im
 * Zeitraum — der Nachweis, dass jede:r Bewohner:in das zusätzliche Betreuungsangebot erhält.
 */
class SocialCareService
{
    /**
     * @return array<int, array{einheiten: int, minuten: int}> resident_id => Bilanz
     */
    public function bilanz(string $von, string $bis): array
    {
        $teilnahmen = BetreuungsTeilnahme::where('teilgenommen', true)
            ->whereHas('angebot', fn ($q) => $q->whereBetween('datum', [$von, $bis]))
            ->with('angebot:id,dauer_minuten')
            ->get();

        $bilanz = [];
        foreach ($teilnahmen as $t) {
            $bilanz[$t->resident_id]['einheiten'] = ($bilanz[$t->resident_id]['einheiten'] ?? 0) + 1;
            $bilanz[$t->resident_id]['minuten'] = ($bilanz[$t->resident_id]['minuten'] ?? 0) + $t->angebot->dauer_minuten;
        }

        return $bilanz;
    }
}
