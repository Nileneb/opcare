<?php

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'super-admin']);
    }

    public function create(User $u): bool
    {
        return $u->hasAnyRole(['admin', 'super-admin']);
    }

    public function update(User $u, User $target): bool
    {
        return $u->hasRole('super-admin') || ($u->hasRole('admin') && $u->tenant_id === $target->tenant_id);
    }
}
