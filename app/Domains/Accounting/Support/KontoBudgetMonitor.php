<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Buchung;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\Konto;
use Illuminate\Support\Carbon;

/**
 * Budget-Auslastung eines Sachkontos im Monat. Der Verbrauch ist der Netto-Abfluss in der natürlichen Richtung
 * der Kontoart (Aufwand/Aktiv: Soll − Haben; Passiv/Ertrag: Haben − Soll) — bei einem Abteilungs-Aufwandskonto
 * also genau die Ausgaben des Monats. Liefert das generische BudgetStatus-Wertobjekt (Ampel/Sperre).
 */
class KontoBudgetMonitor
{
    public function status(Konto $konto, string $monat): BudgetStatus
    {
        $budget = Budget::where('konto_id', $konto->id)->first();

        return new BudgetStatus($budget, $this->verbraucht($konto, $monat));
    }

    public function verbraucht(Konto $konto, string $monat): float
    {
        $start = Carbon::parse($monat)->startOfMonth()->toDateString();
        $ende = Carbon::parse($monat)->endOfMonth()->toDateString();

        $soll = (float) Buchung::where('soll_konto_id', $konto->id)->whereBetween('datum', [$start, $ende])->sum('betrag');
        $haben = (float) Buchung::where('haben_konto_id', $konto->id)->whereBetween('datum', [$start, $ende])->sum('betrag');

        $netto = $konto->typ->sollSeite() ? $soll - $haben : $haben - $soll;

        return max(0.0, round($netto, 2));
    }
}
