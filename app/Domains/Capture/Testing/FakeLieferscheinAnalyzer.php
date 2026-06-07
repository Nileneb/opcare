<?php

namespace App\Domains\Capture\Testing;

use App\Domains\Capture\Contracts\LieferscheinVlmAnalyzer;
use App\Domains\Capture\Data\LieferscheinExtraktion;
use App\Domains\Capture\Data\LieferscheinPositionDaten;

/**
 * Deterministischer VLM-Ersatz für dev/test ohne GPU (analog FakeBelegAnalyzer, gebunden über SPEECH_FAKE).
 */
class FakeLieferscheinAnalyzer implements LieferscheinVlmAnalyzer
{
    public function analysiere(string $imageBase64, string $mimeType): LieferscheinExtraktion
    {
        return new LieferscheinExtraktion(
            lieferant: 'Großhandel Bergisch GmbH',
            datum: today()->toDateString(),
            lieferschein_nr: 'LS-2026-0815',
            konfidenz: 0.9,
            positionen: [
                new LieferscheinPositionDaten(
                    text: 'Weizenmehl Type 405 25kg',
                    menge: 10.0,
                    einheit: 'Sack',
                    einzelpreis: 12.50,
                ),
                new LieferscheinPositionDaten(
                    text: 'Markenbutter 250g',
                    menge: 40.0,
                    einheit: 'Stück',
                    einzelpreis: 1.79,
                    charge_nr: 'CH-A1',
                    mhd: now()->addDays(20)->toDateString(),
                ),
            ],
        );
    }
}
