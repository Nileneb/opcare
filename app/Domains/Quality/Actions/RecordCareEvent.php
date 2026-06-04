<?php

namespace App\Domains\Quality\Actions;

use App\Domains\Quality\Data\CareEventData;
use App\Domains\Quality\Models\CareEvent;

class RecordCareEvent
{
    public function handle(CareEventData $data): CareEvent
    {
        return CareEvent::create([...$data->toArray(), 'reported_by' => $data->reported_by ?? auth()->id()]);
    }
}
