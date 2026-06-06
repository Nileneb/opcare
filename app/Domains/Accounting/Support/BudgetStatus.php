<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Treuhandbudget;

/**
 * Auswertung eines Budgets gegen den bereits verbrauchten Betrag eines Zeitraums: liefert Rest, Auslastung
 * in Prozent und die Ampelfarbe. Ohne hinterlegtes Budget bleibt die Ampel „kein". Generisches Wertobjekt —
 * von der Treuhand-Auszahlung wie von künftigen Wirtschaftsbudgets nutzbar.
 */
class BudgetStatus
{
    public function __construct(
        public readonly ?Treuhandbudget $budget,
        public readonly float $verbraucht,
    ) {}

    public function limit(): ?float
    {
        return $this->budget ? (float) $this->budget->limit_betrag : null;
    }

    public function rest(): ?float
    {
        return $this->budget ? round($this->limit() - $this->verbraucht, 2) : null;
    }

    public function prozent(): ?int
    {
        if (! $this->budget || $this->limit() <= 0) {
            return null;
        }

        return (int) floor($this->verbraucht / $this->limit() * 100);
    }

    /** Würde der zusätzliche Betrag das Limit überschreiten? */
    public function wuerdeUeberschreiten(float $betrag): bool
    {
        return $this->budget !== null && round($this->verbraucht + $betrag, 2) > $this->limit();
    }

    /** Harte Sperre: Budget mit aktiver Sperre, das der zusätzliche Betrag reißen würde. */
    public function istGesperrt(float $betrag): bool
    {
        return $this->budget?->sperre === true && $this->wuerdeUeberschreiten($betrag);
    }

    public function ampel(): string
    {
        if (! $this->budget) {
            return 'kein';
        }
        $p = $this->prozent() ?? 0;
        if ($p >= 100) {
            return 'rot';
        }

        return $p >= $this->budget->warn_prozent ? 'gelb' : 'gruen';
    }
}
