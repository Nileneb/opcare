<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Models\Treuhandbuchung;
use App\Domains\Accounting\Models\Treuhandbudget;
use App\Domains\Accounting\Models\Treuhandkonto;
use Illuminate\Support\Carbon;

/**
 * Berechnet die Budget-Auslastung eines Treuhandkontos im Monat. Der Verbrauch ist der Netto-Abfluss
 * (Auszahlungen minus rückbuchende Korrekturen) des Topfes — so reduziert eine Korrektur den Verbrauch
 * automatisch. Bei kategorie=null gilt das Gesamtbudget über alle Kategorien.
 */
class BudgetMonitor
{
    public function status(Treuhandkonto $konto, ?BarbetragKategorie $kategorie, string $monat): BudgetStatus
    {
        $budget = Treuhandbudget::where('treuhand_konto_id', $konto->id)
            ->where('kategorie', $kategorie?->value)
            ->first();

        return new BudgetStatus($budget, $this->verbraucht($konto, $kategorie, $monat));
    }

    /** Netto-Abfluss (>= 0) des Topfes im Monat. */
    public function verbraucht(Treuhandkonto $konto, ?BarbetragKategorie $kategorie, string $monat): float
    {
        $start = Carbon::parse($monat)->startOfMonth()->toDateString();
        $ende = Carbon::parse($monat)->endOfMonth()->toDateString();

        $query = Treuhandbuchung::where('treuhand_konto_id', $konto->id)
            ->whereBetween('datum', [$start, $ende]);

        if ($kategorie !== null) {
            $query->where('kategorie', $kategorie->value);
        }

        $netto = (float) $query->sum('betrag');

        return max(0.0, round(-$netto, 2));
    }
}
