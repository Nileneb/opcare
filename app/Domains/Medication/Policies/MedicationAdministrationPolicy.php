<?php

namespace App\Domains\Medication\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Medication\Models\MedicationAdministration;

// WHY(append-only): Gaben sind Events — kein Hard-Delete erlaubt. Korrekturen via Status + Notiz.
class MedicationAdministrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function administer(User $user, MedicationAdministration $administration): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function update(User $user, MedicationAdministration $administration): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }
}
