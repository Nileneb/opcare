<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Events\NachrichtGesendet;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\Nachricht;
use App\Domains\Identity\Models\User;
use Illuminate\Validation\ValidationException;

class NachrichtSenden
{
    public function handle(Konversation $k, User $u, string $inhalt): Nachricht
    {
        abort_unless($k->darfSchreiben($u), 403, 'Keine Schreibberechtigung für diese Konversation.');

        $inhalt = trim($inhalt);

        if ($inhalt === '') {
            throw ValidationException::withMessages(['inhalt' => 'Nachricht darf nicht leer sein.']);
        }

        if (mb_strlen($inhalt) > 2000) {
            throw ValidationException::withMessages(['inhalt' => 'Nachricht darf maximal 2000 Zeichen enthalten.']);
        }

        $nachricht = Nachricht::withoutGlobalScopes()->create([
            'tenant_id' => $k->tenant_id,
            'konversation_id' => $k->id,
            'user_id' => $u->id,
            'inhalt' => $inhalt,
        ]);

        NachrichtGesendet::dispatch($k->id, $nachricht->id);

        return $nachricht;
    }
}
