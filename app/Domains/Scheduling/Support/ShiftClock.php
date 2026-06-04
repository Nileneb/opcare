<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Scheduling\Models\Shift;

class ShiftClock
{
    // WHY: zentrale Zeitquelle. Schicht-`timeslots` (JSON: slot => HH:MM) überschreiben den
    // statischen config-Default je Mandant. Null-Rückgabe = keine Schicht-Konfiguration vorhanden.
    public static function for(AdministrationTimeslot $slot): ?string
    {
        if (! app(CurrentTenant::class)->id()) {
            return null;
        }

        $treffer = Shift::query()
            ->where('aktiv', true)
            ->get()
            ->map(fn (Shift $s) => $s->timeslots[$slot->value] ?? null)
            ->filter()
            ->first();

        return $treffer ?: null;
    }
}
