<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Models\CalendarEvent;

class CancelCalendarEvent
{
    // WHY: Termine werden nicht hart gelöscht (Audit/Historie) — Absage über abgesagt_am.
    public function handle(CalendarEvent $event): CalendarEvent
    {
        $event->update(['abgesagt_am' => now()]);

        return $event;
    }
}
