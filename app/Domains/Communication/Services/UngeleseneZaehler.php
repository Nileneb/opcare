<?php

namespace App\Domains\Communication\Services;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class UngeleseneZaehler
{
    public function fuer(User $u): int
    {
        // WHY: Ein JOIN statt N+1-Queries — zählt alle ungelesenen Nachrichten über alle
        // Konversationen des Users in einer Query. Filtert eigene + zurückgezogene raus.
        return (int) DB::table('nachrichten')
            ->join('konversation_teilnehmer', function ($join) use ($u) {
                $join->on('konversation_teilnehmer.konversation_id', '=', 'nachrichten.konversation_id')
                    ->where('konversation_teilnehmer.user_id', '=', $u->id);
            })
            ->where('nachrichten.tenant_id', $u->tenant_id)
            ->where('nachrichten.user_id', '!=', $u->id)
            ->whereNull('nachrichten.geloescht_am')
            ->where(function ($q) {
                $q->whereNull('konversation_teilnehmer.zuletzt_gelesen_am')
                    ->orWhereColumn('nachrichten.created_at', '>', 'konversation_teilnehmer.zuletzt_gelesen_am');
            })
            ->count();
    }
}
