<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Scheduling\Support\ShiftClock;
use Spatie\Permission\PermissionRegistrar;

class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        // WHY: ShiftClock cacht Slot→Uhrzeit je Mandant statisch (N+1-Schutz). Wechselt der
        // Mandanten-Kontext, muss der Cache verworfen werden, sonst leakt fremde Konfiguration.
        ShiftClock::flushCache();
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
