<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;

class RecordVital
{
    public function handle(VitalData $data): VitalReading
    {
        return VitalReading::create([
            ...$data->toArray(),
            'einheit' => VitalType::from($data->typ)->einheit(),
            'gemessen_am' => now(),
        ]);
    }
}
