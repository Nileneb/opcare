<?php

namespace App\Domains\Accounting\Contracts;

/**
 * Eine setzbare Budget-Grenze (Limit + Warn-Schwelle + optionale harte Sperre). Entkoppelt `BudgetStatus` vom
 * konkreten Budget-Topf — so wird dasselbe Auswertungs-/Ampel-Verhalten von der Treuhand-Auszahlung wie vom
 * Hauptbuch-Konto-Budget (und künftigen Töpfen) genutzt. „Budgets öfter mal irgendwo" = ein Muster, ein Status.
 */
interface BudgetGrenze
{
    public function limitBetrag(): float;

    public function warnProzent(): int;

    public function sperreAktiv(): bool;
}
