<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Compliance\Data\StaffingAnalysis;

/**
 * Berechnet den Personalbedarf (Betreuungsschlüssel) einer Einrichtung nach § 113c SGB XI aus dem aktuellen
 * Pflegegrad-Mix der belegten Bewohner und stellt ihn den geplanten Ist-Wochenstunden gegenüber. Eine VZÄ
 * entspricht der Tarif-Wochenstundenzahl × Soll-VZÄ; so wird der Jahres-Stellenwert wochenvergleichbar.
 */
class Betreuungsschluessel
{
    public function analysiere(int $tenantId, float $istWochenstundenGesamt, float $istWochenstundenFachkraft): StaffingAnalysis
    {
        $config = PersonalbemessungDefaults::ensureConfig($tenantId);

        $pgCounts = [];
        foreach (range(1, 5) as $pg) {
            $pgCounts[$pg] = Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->where('pflegegrad', $pg)->count();
        }

        $mult = $config->paw_multiplikator;
        $sollVzaeGesamt = round(PersonalbemessungDefaults::sollVzaeGesamt($pgCounts) * $mult, 4);
        $sollVzaeFachkraft = round(PersonalbemessungDefaults::sollVzaeFachkraft($pgCounts) * $mult, 4);

        return new StaffingAnalysis(
            pgCounts: $pgCounts,
            sollVzaeGesamt: $sollVzaeGesamt,
            sollVzaeFachkraft: $sollVzaeFachkraft,
            sollWochenstundenGesamt: round($sollVzaeGesamt * $config->wochenstunden, 1),
            sollWochenstundenFachkraft: round($sollVzaeFachkraft * $config->wochenstunden, 1),
            istWochenstundenGesamt: round($istWochenstundenGesamt, 1),
            istWochenstundenFachkraft: round($istWochenstundenFachkraft, 1),
        );
    }
}
