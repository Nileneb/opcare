<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Models\Prescription;

class CreatePrescription
{
    public function handle(PrescriptionData $data): Prescription
    {
        return Prescription::create([
            ...$data->toArray(),
            'gueltig_von' => $data->gueltig_von ?? now()->toDateString(),
        ]);
    }
}
