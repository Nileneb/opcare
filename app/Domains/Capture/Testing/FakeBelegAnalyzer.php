<?php

namespace App\Domains\Capture\Testing;

use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Data\BelegExtraktion;

/**
 * Deterministischer VLM-Ersatz für dev/test ohne GPU (analog FakeSisStructurer, gebunden über SPEECH_FAKE).
 */
class FakeBelegAnalyzer implements BelegVlmAnalyzer
{
    public function analysiere(string $imageBase64, string $mimeType): BelegExtraktion
    {
        return new BelegExtraktion(
            belegtyp: 'quittung',
            datum: today()->toDateString(),
            betrag: 24.90,
            waehrung: 'EUR',
            lieferant: 'Demo-Drogeriemarkt',
            positionen: [
                ['text' => 'Pflegeartikel', 'betrag' => 19.90],
                ['text' => 'Hygieneartikel', 'betrag' => 5.00],
            ],
            konfidenz: 0.92,
        );
    }
}
