<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Konto;

/**
 * Wiederverwendbarer Budget-Gate für Hauptbuch-Buchungen: prüft, ob eine Buchung über das Soll-Konto dessen
 * Monatsbudget reißt. Eine aktive Sperre blockiert (hart), sonst wird bei Überschreitung weich gewarnt. Wird von
 * jeder buchenden Stelle genutzt (freie Buchung, Beleg-Capture) — „Budgets öfter mal irgendwo" an einer Stelle.
 */
class BudgetGuard
{
    public function __construct(private readonly KontoBudgetMonitor $monitor) {}

    /**
     * @return array{block: ?string, warn: ?string}
     */
    public function pruefe(int $sollKontoId, float $betrag, string $datum): array
    {
        $konto = Konto::find($sollKontoId);
        if ($konto === null) {
            return ['block' => null, 'warn' => null];
        }

        $status = $this->monitor->status($konto, $datum);
        if (! $status->budget) {
            return ['block' => null, 'warn' => null];
        }

        $limit = number_format((float) $status->limit(), 2, ',', '.');

        if ($status->istGesperrt($betrag)) {
            return ['block' => "Budget gesperrt: {$konto->name} (Limit {$limit} € / Monat überschritten).", 'warn' => null];
        }
        if ($status->wuerdeUeberschreiten($betrag)) {
            return ['block' => null, 'warn' => "Budget überschritten: {$konto->name} (Limit {$limit} € / Monat). Buchung erfasst."];
        }

        return ['block' => null, 'warn' => null];
    }
}
