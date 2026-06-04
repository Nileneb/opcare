<?php

namespace App\Domains\Medication\Support;

use App\Domains\Medication\Enums\AdministrationTimeslot;

class TimeslotClock
{
    public static function for(AdministrationTimeslot $slot): string
    {
        return config('medication.timeslot_clock.'.$slot->value, '12:00');
    }
}
