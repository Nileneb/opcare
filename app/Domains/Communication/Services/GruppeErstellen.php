<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class GruppeErstellen
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(User $ersteller, string $titel, array $userIds): Konversation
    {
        $tenantId = $this->currentTenant->id();

        return DB::transaction(function () use ($ersteller, $titel, $userIds, $tenantId) {
            $konversation = Konversation::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'typ' => KonversationTyp::Gruppe,
                'titel' => $titel,
                'erstellt_von' => $ersteller->id,
            ]);

            $allIds = array_unique(array_merge([$ersteller->id], $userIds));

            foreach ($allIds as $userId) {
                $user = User::withoutGlobalScopes()->find($userId);

                abort_unless(
                    $user !== null && $user->tenant_id === $tenantId,
                    422,
                    "User {$userId} gehört nicht zu diesem Mandanten."
                );

                KonversationTeilnehmer::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'konversation_id' => $konversation->id,
                    'user_id' => $userId,
                    'darf_schreiben' => true,
                ]);
            }

            return $konversation;
        });
    }
}
