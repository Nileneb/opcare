<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\ResidentData;
use App\Domains\Masterdata\Models\Resident;

class CreateResident
{
    public function handle(ResidentData $data): Resident
    {
        return Resident::create($data->toArray());
    }
}
