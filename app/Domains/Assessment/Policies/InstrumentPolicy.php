<?php

namespace App\Domains\Assessment\Policies;

use App\Domains\Identity\Models\User;

class InstrumentPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
