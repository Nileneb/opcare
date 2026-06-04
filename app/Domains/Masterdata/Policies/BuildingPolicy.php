<?php

namespace App\Domains\Masterdata\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Building;

class BuildingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Building $building): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Building $building): bool
    {
        return $user->hasRole('admin');
    }
}
