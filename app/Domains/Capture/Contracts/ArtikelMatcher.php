<?php

namespace App\Domains\Capture\Contracts;

use App\Domains\Capture\Data\ArtikelKandidat;

interface ArtikelMatcher
{
    /**
     * Liefert Artikel-Kandidaten für einen Belegzeilen-Text, score-absteigend sortiert.
     *
     * @return ArtikelKandidat[]
     */
    public function match(string $positionsText, ?int $lieferantId, int $tenantId, int $topK = 5): array;

    /**
     * Merkt sich eine bestätigte Zuordnung (Lerngedächtnis).
     */
    public function merke(string $positionsText, ?int $lieferantId, int $tenantId, int $artikelId): void;
}
