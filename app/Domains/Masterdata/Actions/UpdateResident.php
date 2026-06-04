<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\ResidentData;
use App\Domains\Masterdata\Models\Resident;

class UpdateResident
{
    public function handle(Resident $resident, ResidentData $data): Resident
    {
        $resident->update($data->toArray());

        return $resident;
    }
}
