<?php

namespace App\Domains\CarePlanning\Policies;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Identity\Models\User;

class CareReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function update(User $user, CareReport $r): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }
}
