<?php

namespace App\Domains\Communication\Services;

use App\Domains\Communication\Enums\KonversationTyp;
use App\Domains\Communication\Models\Konversation;
use App\Domains\Communication\Models\KonversationTeilnehmer;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Station;
use Illuminate\Support\Facades\DB;

class StationskanalBeitreten
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(User $u, int $stationId): Konversation
    {
        $tenantId = $this->currentTenant->id();

        $station = Station::withoutGlobalScopes()
            ->where('id', $stationId)
            ->where('tenant_id', $tenantId)
            ->first();

        abort_unless($station !== null, 422, 'Station gehört nicht zu diesem Mandanten.');

        return DB::transaction(function () use ($u, $stationId, $tenantId, $station) {
            $konversation = Konversation::withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'typ' => KonversationTyp::Station,
                    'station_id' => $stationId,
                ],
                [
                    'titel' => $station->name,
                    'erstellt_von' => $u->id,
                ]
            );

            KonversationTeilnehmer::withoutGlobalScopes()->firstOrCreate(
                [
                    'konversation_id' => $konversation->id,
                    'user_id' => $u->id,
                ],
                [
                    'tenant_id' => $tenantId,
                    'darf_schreiben' => true,
                ]
            );

            return $konversation;
        });
    }
}
