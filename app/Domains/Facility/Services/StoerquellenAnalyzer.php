<?php

namespace App\Domains\Facility\Services;

use App\Domains\Facility\Data\StoerquellenBefund;
use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Models\FacilityMeldung;
use App\Domains\Facility\Models\StoerquelleVorsorge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Wertet die häufigsten Störquellen der Haustechnik über ein Zeitfenster (6–12 Monate) aus. Gruppiert die
 * Mängelmeldungen je Betriebsmittel, rankt nach Häufigkeit und markiert je Störquelle, ob bereits eine
 * Notfallvorsorge (Ersatzteile/Reaktionszeit/Sofortmaßnahmen) hinterlegt ist. Meldungen ohne Anlagenbezug
 * werden NICHT verschluckt, sondern als eigene „nicht zugeordnet"-Zeile geführt (Datenhygiene-Hinweis).
 *
 * @return Collection<int, StoerquellenBefund>
 */
class StoerquellenAnalyzer
{
    /** @return Collection<int, StoerquellenBefund> nach Häufigkeit absteigend (volle Liste, nicht gekappt) */
    public function analysiere(int $tenantId, int $monate): Collection
    {
        $ab = now()->subMonths($monate);

        $meldungen = FacilityMeldung::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $ab)
            ->with('asset')
            ->get();

        $vorsorgen = StoerquelleVorsorge::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('aktiv', true)
            ->get();

        return $meldungen
            ->groupBy(fn (FacilityMeldung $m) => $m->asset_id ?? 'ohne')
            ->map(function (Collection $gruppe, $key) use ($vorsorgen): StoerquellenBefund {
                /** @var FacilityMeldung $erste */
                $erste = $gruppe->first();
                $assetId = $key === 'ohne' ? null : (int) $key;

                // Bei der „ohne"-Gruppe gibt es kein Betriebsmittel; sonst ist die Relation eager-geladen
                // und per FK (nullOnDelete) garantiert vorhanden.
                if ($assetId === null) {
                    $bezeichnung = '(ohne Anlagenzuordnung)';
                    $kategorie = null;
                    $vorsorge = null;
                } else {
                    $asset = $erste->asset;
                    $bezeichnung = $asset->bezeichnung;
                    $kategorie = $asset->kategorie;
                    $vorsorge = $vorsorgen->first(fn (StoerquelleVorsorge $v) => $v->deckt($assetId, $kategorie));
                }

                return new StoerquellenBefund(
                    assetId: $assetId,
                    bezeichnung: $bezeichnung,
                    kategorie: $kategorie,
                    anzahl: $gruppe->count(),
                    offen: $gruppe->filter(fn (FacilityMeldung $m) => $m->status !== MeldungStatus::Erledigt)->count(),
                    dringend: $gruppe->filter(fn (FacilityMeldung $m) => $m->prioritaet->badge() === 'red' || $m->prioritaet->badge() === 'amber')->count(),
                    letzteMeldung: $this->juengste($gruppe),
                    hatVorsorge: $vorsorge !== null,
                    vorsorgeId: $vorsorge?->id,
                );
            })
            ->sortByDesc(fn (StoerquellenBefund $b) => sprintf('%06d%06d', $b->anzahl, $b->dringend))
            ->values();
    }

    /** @param Collection<int, FacilityMeldung> $gruppe */
    private function juengste(Collection $gruppe): ?Carbon
    {
        return $gruppe
            ->map(fn (FacilityMeldung $m) => $m->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }

    /**
     * Vorschlags-Kategorie für eine neue Vorsorge anhand der Störquelle (für die UI-Vorbefüllung).
     */
    public function kategorieVorschlag(?AssetKategorie $kategorie): AssetKategorie
    {
        return $kategorie ?? AssetKategorie::Sonstiges;
    }
}
