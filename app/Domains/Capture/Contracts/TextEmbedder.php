<?php

namespace App\Domains\Capture\Contracts;

interface TextEmbedder
{
    /**
     * Berechnet einen Embedding-Vektor für den übergebenen Text.
     * Gibt null zurück, wenn das Modell nicht verfügbar ist (kein stiller Fehler — wird geloggt).
     *
     * @return float[]|null
     */
    public function embed(string $text): ?array;

    public function model(): string;
}
