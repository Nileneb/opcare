<?php

namespace App\Domains\Assessment\Policies;

use App\Domains\Identity\Models\User;

class AssessmentPolicy
{
    public function conduct(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
