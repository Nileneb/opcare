<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }
}
