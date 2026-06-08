<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Models\Konversation;
use App\Domains\Identity\Models\User;

class KonversationGelesen
{
    public function handle(Konversation $k, User $u): void
    {
        $k->teilnehmer()
            ->where('user_id', $u->id)
            ->update(['zuletzt_gelesen_am' => now()]);
    }
}
