<?php

namespace App\Domains\Capture\Contracts;

use App\Domains\Capture\Data\LieferscheinExtraktion;

interface LieferscheinVlmAnalyzer
{
    /** Analysiert ein Lieferschein- oder Rechnungsfoto (base64) und liefert strukturierte Positionen (Vorschlag, nicht autoritativ). */
    public function analysiere(string $imageBase64, string $mimeType): LieferscheinExtraktion;
}
