<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Data\BuildingData;
use App\Domains\Masterdata\Models\Building;

class CreateBuilding
{
    public function handle(BuildingData $data): Building
    {
        return Building::create(['name' => $data->name]);
    }
}
