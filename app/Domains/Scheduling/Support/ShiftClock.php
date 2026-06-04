<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Scheduling\Models\Shift;

class ShiftClock
{
    // WHY(perf): GenerateAdministrations ruft for() je Slot × je Tag auf (N+1 über die Gaben-
    // Generierung). Innerhalb eines Requests ändert sich die Schicht-Konfiguration nicht, daher
    // werden die Slot→Uhrzeit-Treffer je Mandant einmal materialisiert und gecacht.
    private static array $cache = [];

    // WHY: zentrale Zeitquelle. Schicht-`timeslots` (JSON: slot => HH:MM) überschreiben den
    // statischen config-Default je Mandant. Null-Rückgabe = keine Schicht-Konfiguration vorhanden.
    public static function for(AdministrationTimeslot $slot): ?string
    {
        $tenantId = app(CurrentTenant::class)->id();
        if (! $tenantId) {
            return null;
        }

        return self::slotMap($tenantId)[$slot->value] ?? null;
    }

    /** @return array<string, string> slot-value => HH:MM, erster aktiver Treffer je Slot */
    private static function slotMap(int $tenantId): array
    {
        return self::$cache[$tenantId] ??= Shift::query()
            ->where('aktiv', true)
            ->get()
            ->reduce(function (array $map, Shift $s): array {
                foreach ($s->timeslots ?? [] as $slot => $zeit) {
                    if ($zeit && ! isset($map[$slot])) {
                        $map[$slot] = $zeit;
                    }
                }

                return $map;
            }, []);
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
