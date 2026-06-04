<?php

namespace App\Domains\Medication\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Medication\Models\Prescription;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
