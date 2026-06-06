<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Models\StaffingConfig;

/**
 * Personalanhaltswerte (PAW) nach § 113c Abs. 1 SGB XI (Stand 01.07.2023, bundeseinheitlich): Vollzeit-
 * äquivalente je Bewohner und Pflegegrad, getrennt nach Qualifikationsstufe (QN1+2 Hilfskraft, QN3 Assistenz,
 * QN4 Pflegefachkraft). Diese Werte sind die gesetzliche Obergrenze — Code-Konstante, weil bundeseinheitlich;
 * BMG-Review alle 2 Jahre → bei Änderung `VERSION` hochzählen. Einrichtungsspezifische Stellschrauben
 * (Tarif-Wochenstunden, Multiplikator) stehen in `StaffingConfig`.
 */
class PersonalbemessungDefaults
{
    public const VERSION = '2023-07-01';

    /** @var array<string, array<int, float>> qn => [pg => VZÄ] */
    public const PAW = [
        'qn12' => [1 => 0.0872, 2 => 0.1202, 3 => 0.1449, 4 => 0.1627, 5 => 0.1758],
        'qn3' => [1 => 0.0564, 2 => 0.0675, 3 => 0.1074, 4 => 0.1413, 5 => 0.1102],
        'qn4' => [1 => 0.0770, 2 => 0.1037, 3 => 0.1551, 4 => 0.2463, 5 => 0.3842],
    ];

    public static function ensureConfig(int $tenantId): StaffingConfig
    {
        return StaffingConfig::firstOrCreate(['tenant_id' => $tenantId]);
    }

    /**
     * Soll-VZÄ gesamt aus dem Pflegegrad-Mix.
     *
     * @param  array<int, int>  $pgCounts  pg => Anzahl Bewohner
     */
    public static function sollVzaeGesamt(array $pgCounts): float
    {
        return self::summe(['qn12', 'qn3', 'qn4'], $pgCounts);
    }

    /** @param  array<int, int>  $pgCounts */
    public static function sollVzaeFachkraft(array $pgCounts): float
    {
        return self::summe(['qn4'], $pgCounts);
    }

    /**
     * @param  array<int, string>  $stufen
     * @param  array<int, int>  $pgCounts
     */
    private static function summe(array $stufen, array $pgCounts): float
    {
        $sum = 0.0;
        foreach ($stufen as $stufe) {
            foreach (self::PAW[$stufe] as $pg => $vzae) {
                $sum += ($pgCounts[$pg] ?? 0) * $vzae;
            }
        }

        return round($sum, 4);
    }
}
