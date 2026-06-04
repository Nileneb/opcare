<?php

namespace App\Domains\Medication\Support;

use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Scheduling\Support\ShiftClock;

class TimeslotClock
{
    // WHY: Schicht-Konfiguration (Plan 8) hat Vorrang vor dem statischen config-Default (Plan 5).
    public static function for(AdministrationTimeslot $slot): string
    {
        return ShiftClock::for($slot)
            ?? config('medication.timeslot_clock.'.$slot->value, '12:00');
    }
}
