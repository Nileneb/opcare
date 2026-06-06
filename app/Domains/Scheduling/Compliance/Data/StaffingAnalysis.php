<?php

namespace App\Domains\Scheduling\Compliance\Data;

/**
 * Ergebnis der Betreuungsschlüssel-Berechnung (§ 113c SGB XI) für eine Planungswoche: Soll aus dem
 * Pflegegrad-Mix vs. geplante Ist-Stunden, jeweils gesamt und für Fachkräfte, mit Ampel.
 */
class StaffingAnalysis
{
    /** @param  array<int, int>  $pgCounts */
    public function __construct(
        public readonly array $pgCounts,
        public readonly float $sollVzaeGesamt,
        public readonly float $sollVzaeFachkraft,
        public readonly float $sollWochenstundenGesamt,
        public readonly float $sollWochenstundenFachkraft,
        public readonly float $istWochenstundenGesamt,
        public readonly float $istWochenstundenFachkraft,
    ) {}

    public function ampelGesamt(): string
    {
        return self::ampel($this->istWochenstundenGesamt, $this->sollWochenstundenGesamt);
    }

    public function ampelFachkraft(): string
    {
        return self::ampel($this->istWochenstundenFachkraft, $this->sollWochenstundenFachkraft);
    }

    public function deckungGesamt(): int
    {
        return $this->sollWochenstundenGesamt > 0
            ? (int) round($this->istWochenstundenGesamt / $this->sollWochenstundenGesamt * 100)
            : 100;
    }

    public function deckungFachkraft(): int
    {
        return $this->sollWochenstundenFachkraft > 0
            ? (int) round($this->istWochenstundenFachkraft / $this->sollWochenstundenFachkraft * 100)
            : 100;
    }

    private static function ampel(float $ist, float $soll): string
    {
        if ($soll <= 0) {
            return 'gruen';
        }
        $ratio = $ist / $soll;

        return $ratio >= 1.0 ? 'gruen' : ($ratio >= 0.85 ? 'gelb' : 'rot');
    }
}
