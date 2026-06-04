<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;

class ShiftPolicy
{
    // WHY: Dienstplan/Schicht-Pflege ist Leitungssache. super-admin erhält Bypass über Gate::before.
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
