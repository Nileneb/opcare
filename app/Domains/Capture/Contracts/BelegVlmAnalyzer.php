<?php

namespace App\Domains\Capture\Contracts;

use App\Domains\Capture\Data\BelegExtraktion;

interface BelegVlmAnalyzer
{
    /** Analysiert ein Belegfoto (base64) und liefert die strukturierte Extraktion (Vorschlag, nicht autoritativ). */
    public function analysiere(string $imageBase64, string $mimeType): BelegExtraktion;
}
