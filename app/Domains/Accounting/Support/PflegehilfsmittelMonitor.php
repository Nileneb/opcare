<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregiert bewohnerbezogene Pflegehilfsmittel-Kosten (§ 40 SGB XI) je Kalendermonat.
 *
 * COMPLIANCE-HINWEIS: Die 42-€-Pauschale gilt ausschließlich ambulant/häuslich. In vollstationären
 * Einrichtungen trägt der Träger die Kosten über den Pflegesatz. Diese Auswertung dient dort nur
 * der internen Kostentransparenz, nicht der Abrechnung gegenüber Pflegekassen.
 */
class PflegehilfsmittelMonitor
{
    // WHY(§ 40 Abs. 2 SGB XI): Gesetzliche Pauschale 42 €/Monat (Stand 2025/2026, nur ambulant).
    public const PAUSCHALE = 42.00;

    /**
     * Verbrauchskosten aller Pflegehilfsmittel je Bewohner für einen Kalendermonat.
     *
     * @param  string  $monat  Format 'Y-m' (z. B. '2026-06')
     * @return array<int, array{resident: Resident, summe: float, prozent: int, ampel: 'gruen'|'amber'|'rot'}>
     */
    public function verbrauchProBewohner(int $tenantId, string $monat): array
    {
        $period = Carbon::createFromFormat('Y-m', $monat);
        $monatsStart = $period->copy()->startOfMonth()->toDateString();
        $monatsEnde = $period->copy()->endOfMonth()->toDateString();

        // Join über bewegung→datum für Monatsfilter; schicht→artikel für pflegehilfsmittel-Flag.
        // WHY(date-Cast-Falle): datum ist als 'date' gecastet (gespeichert ohne Uhrzeit), aber
        // whereBetween schneidet den Bis-Tag aus — daher whereDate statt whereBetween.
        // WHY(DB::table statt Eloquent): selectRaw-Aggregate sind kein Model-Property — Query-Builder
        // gibt stdClass zurück, PHPStan kann dann ->resident_id und ->summe_kosten sauber typisieren.
        $rows = DB::table('schichtabgaenge')
            ->where('schichtabgaenge.tenant_id', $tenantId)
            ->whereNotNull('schichtabgaenge.resident_id')
            ->join('lagerbewegungen', 'lagerbewegungen.id', '=', 'schichtabgaenge.bewegung_id')
            ->whereDate('lagerbewegungen.datum', '>=', $monatsStart)
            ->whereDate('lagerbewegungen.datum', '<=', $monatsEnde)
            ->join('lagerschichten', 'lagerschichten.id', '=', 'schichtabgaenge.schicht_id')
            ->join('artikel', 'artikel.id', '=', 'lagerschichten.artikel_id')
            ->where('artikel.pflegehilfsmittel', true)
            ->selectRaw('schichtabgaenge.resident_id, SUM(schichtabgaenge.menge * schichtabgaenge.einstandspreis) as summe_kosten')
            ->groupBy('schichtabgaenge.resident_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $residentIds = $rows->pluck('resident_id')->unique();
        $residents = Resident::whereIn('id', $residentIds)->get()->keyBy('id');

        $result = [];
        foreach ($rows as $row) {
            $resident = $residents->get($row->resident_id);
            if ($resident === null) {
                continue;
            }

            $summe = round((float) $row->summe_kosten, 2);
            $prozent = (int) round($summe / self::PAUSCHALE * 100);
            $ampel = match (true) {
                $prozent >= 100 => 'rot',
                $prozent >= 80 => 'amber',
                default => 'gruen',
            };

            $result[] = [
                'resident' => $resident,
                'summe' => $summe,
                'prozent' => $prozent,
                'ampel' => $ampel,
            ];
        }

        return $result;
    }
}
