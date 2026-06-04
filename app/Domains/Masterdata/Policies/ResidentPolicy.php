<?php

namespace App\Domains\Masterdata\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function view(User $user, Resident $resident): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }

    public function update(User $user, Resident $resident): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }

    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasRole('admin');
    }
}
