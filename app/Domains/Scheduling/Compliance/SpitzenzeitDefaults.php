<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Models\Spitzenzeit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Standard-Bedarfsspitzen einer Pflegeeinrichtung (Mahlzeiten + Morgen-Grundpflege) als editierbarer Katalog
 * je Einrichtung. Idempotent geseedet — die Soll-Personenzahl ist ein einrichtungsspezifischer Richtwert, den
 * die Leitung anpasst.
 */
class SpitzenzeitDefaults
{
    /** @var list<array{name: string, beginn: string, ende: string, soll_personen: int, nur_werktags: bool}> */
    public const KATALOG = [
        ['name' => 'Morgendliche Grundpflege', 'beginn' => '06:30', 'ende' => '09:00', 'soll_personen' => 3, 'nur_werktags' => false],
        ['name' => 'Frühstück', 'beginn' => '08:00', 'ende' => '09:30', 'soll_personen' => 2, 'nur_werktags' => false],
        ['name' => 'Mittagessen', 'beginn' => '11:30', 'ende' => '13:30', 'soll_personen' => 3, 'nur_werktags' => false],
        ['name' => 'Abendversorgung', 'beginn' => '17:30', 'ende' => '19:30', 'soll_personen' => 2, 'nur_werktags' => false],
    ];

    /** @return Collection<int, Spitzenzeit> */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::KATALOG as $row) {
            Spitzenzeit::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                ['beginn' => $row['beginn'], 'ende' => $row['ende'], 'soll_personen' => $row['soll_personen'], 'nur_werktags' => $row['nur_werktags'], 'aktiv' => true],
            );
        }

        return Spitzenzeit::where('tenant_id', $tenantId)->orderBy('beginn')->get();
    }
}
