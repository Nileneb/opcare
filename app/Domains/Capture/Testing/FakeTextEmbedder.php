<?php

namespace App\Domains\Capture\Testing;

use App\Domains\Capture\Contracts\TextEmbedder;

/**
 * Deterministischer Embedding-Ersatz für dev/test ohne GPU.
 * Dimension 8, Werte aus CRC32 des Textes — stabil für gleiche Eingaben.
 */
class FakeTextEmbedder implements TextEmbedder
{
    public function embed(string $text): ?array
    {
        $vector = [];
        for ($i = 0; $i < 8; $i++) {
            $vector[] = (crc32($text.$i) % 1000) / 1000;
        }

        return $vector;
    }

    public function model(): string
    {
        return 'fake-embed';
    }
}
