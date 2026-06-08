<?php

namespace App\Domains\Brandschutz\Services;

use App\Domains\Brandschutz\Models\Brandschutzbegehung;
use App\Domains\Brandschutz\Models\Brandschutzmangel;
use App\Domains\Brandschutz\Models\Brandschutzordnung;
use App\Domains\Brandschutz\Models\Raeumungsuebung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tenant-scoped Übersicht für Brandschutz-Badges und Ampeln.
 * Norm-Anker: § 10 ArbSchG, ASR A2.2/A2.3, DIN 14096, DGUV 205-001.
 * Frist-Status ausschließlich aus Model-Methoden — keine divergente Query (SSOT-Lektion).
 */
class BrandschutzMonitor
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Je Bereich die jüngste Begehung (max begangen_am).
     * Max-Semantik: ein nachgetragenes älteres Datum darf die Frist nicht zurücksetzen.
     *
     * @return Collection<int, Brandschutzbegehung>
     */
    public function aktuelleBegehungen(): Collection
    {
        return Brandschutzbegehung::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->with('maengel')
            ->get()
            // WHY: sekundär nach id absteigend, damit bei gleichem begangen_am (date-Cast, ohne Zeit)
            // deterministisch die zuletzt erfasste Begehung je Bereich „jüngste" ist.
            ->sortByDesc(fn (Brandschutzbegehung $b): string => sprintf('%s-%012d', $b->begangen_am->format('Y-m-d'), $b->id))
            ->unique('bereich')
            ->values();
    }

    public function aktuelleUebung(): ?Raeumungsuebung
    {
        return Raeumungsuebung::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->orderByDesc('durchgefuehrt_am')
            ->first();
    }

    public function offeneMaengelAnzahl(): int
    {
        return Brandschutzmangel::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->whereNull('behoben_am')
            ->count();
    }

    /**
     * Summe überfälliger Elemente:
     * - Brandschutzordnungen mit status === 'ueberfaellig'
     * - jüngste Begehung je Bereich mit istUeberfaellig()
     * - jüngste Räumungsübung mit istUeberfaellig()
     *
     * Entwürfe werden nicht als überfällig gezählt (eigene Kategorie).
     */
    public function ueberfaelligeAnzahl(): int
    {
        $ordnungen = Brandschutzordnung::query()
            ->where('tenant_id', $this->currentTenant->id())
            ->get()
            ->filter(fn (Brandschutzordnung $o) => $o->status() === 'ueberfaellig')
            ->count();

        $begehungen = $this->aktuelleBegehungen()
            ->filter(fn (Brandschutzbegehung $b) => $b->istUeberfaellig())
            ->count();

        $uebung = $this->aktuelleUebung();
        $uebungUeberfaellig = $uebung !== null && $uebung->istUeberfaellig() ? 1 : 0;

        return $ordnungen + $begehungen + $uebungUeberfaellig;
    }
}
