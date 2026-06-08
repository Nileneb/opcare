<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class AnkuendigungskanalHolen
{
    private const STAFF_ROLES = ['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'];

    public function handle(int $tenantId): Konversation
    {
        return DB::transaction(function () use ($tenantId) {
            $konversation = Konversation::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'typ' => KonversationTyp::Ankuendigung,
                ],
                [
                    'titel' => 'Ankündigungen',
                ]
            );

            $staffUsers = User::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->role(self::STAFF_ROLES)
                ->get();

            foreach ($staffUsers as $user) {
                KonversationTeilnehmer::withoutGlobalScopes()->firstOrCreate(
                    [
                        'konversation_id' => $konversation->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        // WHY: Ankündigung = Lese-Kanal für Nicht-Admins; Schreibrecht via darfSchreiben() per Rolle
                        'darf_schreiben' => false,
                    ]
                );
            }

            return $konversation;
        });
    }
}
