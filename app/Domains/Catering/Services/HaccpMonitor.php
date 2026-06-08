<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\Temperaturmessung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;

/**
 * Tagesblatt-Übersicht für den HACCP-Monitor: je aktivem Messpunkt die heutigen Messungen
 * und offene Abweichungen (VO (EG) 852/2004 Art. 5 Abs. 2 lit. f – Dokumentation).
 */
class HaccpMonitor
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  string|null  $datum  Datum im Format 'Y-m-d'; null = heute
     * @return array<int, array{messpunkt: HaccpMesspunkt, messungen_heute: Temperaturmessung[], offene_abweichung: bool}>
     */
    public function tagesblatt(?string $datum = null): array
    {
        $tag = $datum !== null ? Carbon::parse($datum) : Carbon::today();
        $tenantId = $this->currentTenant->id();

        $messpunkte = HaccpMesspunkt::query()
            ->where('tenant_id', $tenantId)
            ->where('aktiv', true)
            ->with(['messungen' => function ($q) use ($tag): void {
                $q->whereDate('gemessen_am', $tag)->orderBy('gemessen_am');
            }])
            ->get();

        return $messpunkte->map(function (HaccpMesspunkt $mp): array {
            return [
                'messpunkt' => $mp,
                'messungen_heute' => $mp->messungen->all(),
                'offene_abweichung' => $mp->offeneAbweichung(),
            ];
        })->values()->all();
    }
}
