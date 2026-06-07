<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Lagerschicht;

/**
 * Charge/Los-Rückverfolgung nach Art. 18 VO (EG) 178/2002.
 *
 * „Eine Stufe zurück" (Lieferant): Pflicht gemäß Art. 18 Abs. 2.
 * „Intern vorwärts" (Bewohner/Abteilung): kein Art.-18-Pflicht-Element (Endverbraucher),
 * aber wertvoller interner Rückruf-Mehrwert.
 */
class Chargenverfolgung
{
    /**
     * Verfolge eine Charge tenantscoped — gibt alle Schichten + Abgangskette zurück.
     *
     * @return array<int, array{
     *     schicht: Lagerschicht,
     *     artikel: string,
     *     abteilung: string,
     *     lieferant: string|null,
     *     mhd: string|null,
     *     menge_eingang: float,
     *     menge_rest: float,
     *     abgaenge: array<int, array{datum: string, menge: float, abteilung: string, resident: string|null, notiz: string|null}>
     * }>
     */
    public function verfolge(string $chargeNr, int $tenantId): array
    {
        $schichten = Lagerschicht::with(['artikel', 'lieferant', 'abgaenge.bewegung', 'abgaenge.resident'])
            ->where('tenant_id', $tenantId)
            ->where('charge_nr', $chargeNr)
            ->orderBy('eingangsdatum')
            ->get();

        return $schichten->map(function (Lagerschicht $schicht) {
            $abgaenge = $schicht->abgaenge->map(function ($abgang) use ($schicht) {
                return [
                    'datum' => $abgang->bewegung->datum->toDateString(),
                    'menge' => (float) $abgang->menge,
                    'abteilung' => $schicht->artikel->abteilung->label(),
                    'resident' => $abgang->resident?->name,
                    'notiz' => $abgang->bewegung->notiz,
                ];
            })->values()->all();

            return [
                'schicht' => $schicht,
                'artikel' => $schicht->artikel->name,
                'abteilung' => $schicht->artikel->abteilung->label(),
                'lieferant' => $schicht->lieferant?->name,
                'mhd' => $schicht->mhd?->toDateString(),
                'menge_eingang' => (float) $schicht->menge_eingang,
                'menge_rest' => (float) $schicht->menge_rest,
                'abgaenge' => $abgaenge,
            ];
        })->values()->all();
    }
}
