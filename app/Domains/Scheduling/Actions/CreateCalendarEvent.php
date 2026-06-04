<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Models\RecurrenceRule;
use Illuminate\Support\Facades\DB;

class CreateCalendarEvent
{
    public function handle(CalendarEventData $data): CalendarEvent
    {
        return DB::transaction(function () use ($data) {
            $ruleId = null;
            if ($data->recurrence) {
                $ruleId = RecurrenceRule::create($data->recurrence->toArray())->id;
            }

            return CalendarEvent::create([
                'resident_id' => $data->resident_id,
                'type' => $data->type,
                'titel' => $data->titel,
                'beschreibung' => $data->beschreibung,
                'beginnt_am' => $data->beginnt_am,
                'endet_am' => $data->endet_am,
                'ganztaegig' => $data->ganztaegig,
                'recurrence_rule_id' => $ruleId,
                'created_by' => $data->created_by,
            ]);
        });
    }
}
