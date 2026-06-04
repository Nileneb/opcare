<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;

class CreateTenant
{
    public function handle(TenantData $data): Tenant
    {
        return Tenant::create($data->toArray());
    }
}
