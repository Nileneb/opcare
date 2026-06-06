<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Contracts\BudgetGrenze;

/**
 * Auswertung eines Budgets gegen den bereits verbrauchten Betrag eines Zeitraums: liefert Rest, Auslastung
 * in Prozent und die Ampelfarbe. Ohne hinterlegtes Budget bleibt die Ampel „kein". Generisches Wertobjekt —
 * von der Treuhand-Auszahlung wie vom Hauptbuch-Konto-Budget nutzbar (entkoppelt über BudgetGrenze).
 */
class BudgetStatus
{
    public function __construct(
        public readonly ?BudgetGrenze $budget,
        public readonly float $verbraucht,
    ) {}

    public function limit(): ?float
    {
        return $this->budget?->limitBetrag();
    }

    public function rest(): ?float
    {
        return $this->budget ? round($this->budget->limitBetrag() - $this->verbraucht, 2) : null;
    }

    public function prozent(): ?int
    {
        if (! $this->budget || $this->budget->limitBetrag() <= 0) {
            return null;
        }

        return (int) floor($this->verbraucht / $this->budget->limitBetrag() * 100);
    }

    /** Würde der zusätzliche Betrag das Limit überschreiten? */
    public function wuerdeUeberschreiten(float $betrag): bool
    {
        return $this->budget !== null && round($this->verbraucht + $betrag, 2) > $this->budget->limitBetrag();
    }

    /** Harte Sperre: Budget mit aktiver Sperre, das der zusätzliche Betrag reißen würde. */
    public function istGesperrt(float $betrag): bool
    {
        return $this->budget?->sperreAktiv() === true && $this->wuerdeUeberschreiten($betrag);
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

        return $p >= $this->budget->warnProzent() ? 'gelb' : 'gruen';
    }
}
