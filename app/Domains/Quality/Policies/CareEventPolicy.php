<?php

namespace App\Domains\Quality\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Quality\Models\CareEvent;

class CareEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function update(User $user, CareEvent $event): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }
}
