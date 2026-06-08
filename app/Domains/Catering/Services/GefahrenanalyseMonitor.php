<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\Gefahrenanalyse;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant-scoped Übersicht aller HACCP-Gefahrenanalysen mit Frist-Ampel, offenen Lenkungen und Lücken.
 * Norm-Anker: VO (EG) 852/2004 Art. 5 (HACCP-Dokumentation + Verifizierung).
 */
class GefahrenanalyseMonitor
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Alle Gefahrenanalysen des aktuellen Tenants, eager-geladen für Frist-Ampel und SSOT-Lücken.
     *
     * @return Collection<int, Gefahrenanalyse>
     */
    public function status(): Collection
    {
        return Gefahrenanalyse::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->with(['gefahren.lenkungsmassnahmen', 'gefahren.messpunkt'])
            ->orderBy('prozessschritt')
            ->get();
    }

    public function ueberfaelligeAnzahl(): int
    {
        return $this->status()->filter(fn (Gefahrenanalyse $a) => $a->istUeberfaellig())->count();
    }

    public function mitLueckenAnzahl(): int
    {
        return $this->status()->filter(fn (Gefahrenanalyse $a) => $a->hatLuecke())->count();
    }
}
