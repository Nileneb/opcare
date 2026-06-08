<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\Reinigungsaufgabe;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;

/**
 * Übersicht aller aktiven Reinigungsaufgaben mit Fälligkeits-Ampel (Eigenkontroll-Dokumentation).
 * Norm-Anker: VO (EG) 852/2004 Anhang II, LMHV §§ 3/4.
 */
class ReinigungsplanMonitor
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @return array<int, array{aufgabe: Reinigungsaufgabe, status: string, naechste: Carbon|null}>
     */
    public function status(): array
    {
        $tenantId = $this->currentTenant->id();

        $aufgaben = Reinigungsaufgabe::query()
            ->where('tenant_id', $tenantId)
            ->where('aktiv', true)
            ->get();

        return $aufgaben->map(function (Reinigungsaufgabe $aufgabe): array {
            return [
                'aufgabe' => $aufgabe,
                'status' => $aufgabe->faelligkeitsStatus(),
                'naechste' => $aufgabe->naechsteFaelligkeit(),
            ];
        })->values()->all();
    }
}
