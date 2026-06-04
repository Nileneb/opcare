<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\PrescriptionSchedule;

class AddSchedule
{
    public function handle(Prescription $prescription, ScheduleData $data): PrescriptionSchedule
    {
        return $prescription->schedules()->create($data->toArray());
    }
}
