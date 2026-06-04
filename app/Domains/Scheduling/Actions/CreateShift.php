<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ShiftData;
use App\Domains\Scheduling\Models\Shift;

class CreateShift
{
    public function handle(ShiftData $data): Shift
    {
        return Shift::create($data->toArray());
    }
}
