<?php
namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

class TenantPolicy
{
    public function viewAny(User $u): bool { return $u->hasRole('super-admin'); }
    public function create(User $u): bool { return $u->hasRole('super-admin'); }
    public function update(User $u, Tenant $t): bool { return $u->hasRole('super-admin'); }
}
