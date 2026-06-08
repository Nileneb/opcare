<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Models\Nachricht;
use App\Domains\Identity\Models\User;

class NachrichtZurueckziehen
{
    public function handle(Nachricht $n, User $u): void
    {
        abort_unless($n->user_id === $u->id, 403, 'Nur eigene Nachrichten können zurückgezogen werden.');

        abort_unless(
            $n->created_at !== null && $n->created_at->gt(now()->subMinutes(15)),
            422,
            'Frist abgelaufen — Nachricht kann nicht mehr zurückgezogen werden.'
        );

        $n->update(['geloescht_am' => now()]);
    }
}
