<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

class DirektnachrichtOeffnen
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(User $ich, int $partnerUserId): Konversation
    {
        $tenantId = $this->currentTenant->id();

        $partner = User::withoutGlobalScopes()->find($partnerUserId);

        abort_unless(
            $partner !== null && $partner->tenant_id === $tenantId,
            422,
            'Partner gehört nicht zu diesem Mandanten.'
        );

        $staffRoles = ['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche', 'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'];

        abort_unless(
            $partner->hasAnyRole($staffRoles),
            403,
            'Partner ist kein aktives Mitarbeitender.'
        );

        return DB::transaction(function () use ($ich, $partnerUserId, $tenantId) {
            // WHY: Dedupe — suche eine bestehende Direkt-Konversation, deren Teilnehmer-Set
            // exakt {ich, partner} entspricht (auch bei umgekehrter Reihenfolge).
            // HAVING auf subquery-count funktioniert in SQLite nicht zusammen mit whereHas;
            // stattdessen PHP-Filter auf exactly-2 nach den whereHas-Subqueries.
            $existing = Konversation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('typ', KonversationTyp::Direkt)
                ->whereHas('teilnehmer', fn ($q) => $q->where('user_id', $ich->id))
                ->whereHas('teilnehmer', fn ($q) => $q->where('user_id', $partnerUserId))
                ->get()
                ->first(fn ($k) => $k->teilnehmer()->count() === 2);

            if ($existing !== null) {
                return $existing;
            }

            $konversation = Konversation::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'typ' => KonversationTyp::Direkt,
                'titel' => null,
                'erstellt_von' => $ich->id,
            ]);

            KonversationTeilnehmer::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'konversation_id' => $konversation->id,
                'user_id' => $ich->id,
                'darf_schreiben' => true,
            ]);

            KonversationTeilnehmer::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'konversation_id' => $konversation->id,
                'user_id' => $partnerUserId,
                'darf_schreiben' => true,
            ]);

            return $konversation;
        });
    }
}
