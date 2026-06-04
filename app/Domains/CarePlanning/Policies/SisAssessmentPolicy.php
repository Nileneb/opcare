<?php

namespace App\Domains\CarePlanning\Policies;

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\User;

class SisAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function view(User $user, SisAssessment $s): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }

    public function update(User $user, SisAssessment $s): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
