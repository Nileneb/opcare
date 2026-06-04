<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\CareMeasureData;
use App\Domains\CarePlanning\Models\CareMeasure;

class CreateCareMeasure
{
    public function handle(CareMeasureData $data): CareMeasure
    {
        return CareMeasure::create([
            'resident_id' => $data->resident_id,
            'themenfeld' => $data->themenfeld,
            'beschreibung' => $data->beschreibung,
            'ziel' => $data->ziel,
            'verantwortlich' => $data->verantwortlich,
        ]);
    }
}
