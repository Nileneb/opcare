<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

// WHY(hybrid-tenancy): einzige Stelle, die "welcher Mandant gilt" entscheidet.
// Bei späterem DB-per-Tenant wird NUR diese Klasse ausgetauscht.
class TenantResolver
{
    public function resolveFor(User $user, ?int $sessionTenantId): ?Tenant
    {
        if ($sessionTenantId && $user->isSuperAdmin()) {
            return Tenant::aktiv()->find($sessionTenantId) ?? $user->tenant;
        }

        return $user->tenant;
    }
}
