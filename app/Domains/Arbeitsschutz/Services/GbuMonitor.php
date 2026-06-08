<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant-scoped Übersicht aller GBUs mit Frist-Ampel und offenen Maßnahmen.
 * Norm-Anker: § 6 ArbSchG (Dokumentation), § 3 Abs. 1 ArbSchG (Fortschreibungs-Pflicht).
 */
class GbuMonitor
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Alle GBUs des aktuellen Tenants, eager-geladen für Frist-Ampel und SSOT-Maßnahmen.
     * Frist-Status aus den Model-Methoden — keine divergente Query (SSOT-Lektion).
     *
     * @return Collection<int, Gefaehrdungsbeurteilung>
     */
    public function status(): Collection
    {
        return Gefaehrdungsbeurteilung::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->with('gefaehrdungen.massnahmen')
            ->get();
    }

    public function ueberfaelligeAnzahl(): int
    {
        return $this->status()->filter(fn (Gefaehrdungsbeurteilung $gbu) => $gbu->istUeberfaellig())->count();
    }
}
