<?php

namespace App\Domains\Capture\Data;

use Spatie\LaravelData\Data;

/**
 * Kandidat für die Artikel-Zuordnung einer Belegposition.
 *
 * @property string $quelle 'gedaechtnis' | 'embedding'
 */
class ArtikelKandidat extends Data
{
    public function __construct(
        public int $artikel_id,
        public string $name,
        public float $score,
        public string $quelle,
    ) {}
}
