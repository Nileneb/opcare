<?php
namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Data\TenantData;
use App\Domains\Identity\Models\Tenant;

class UpdateTenant
{
    public function handle(Tenant $tenant, TenantData $data): Tenant
    {
        $tenant->update($data->toArray());
        return $tenant;
    }
}
