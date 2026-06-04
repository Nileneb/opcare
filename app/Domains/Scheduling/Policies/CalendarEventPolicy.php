<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Models\CalendarEvent;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // alle eingeloggten Rollen dürfen Termine sehen
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']);
    }

    public function cancel(User $user, CalendarEvent $event): bool
    {
        return $user->hasAnyRole(['admin', 'pflegefachkraft']);
    }
}
