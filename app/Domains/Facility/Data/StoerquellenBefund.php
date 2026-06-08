<?php

namespace App\Domains\Facility\Data;

use App\Domains\Facility\Enums\AssetKategorie;
use Illuminate\Support\Carbon;

/**
 * Eine ausgewertete Störquelle im Ranking (Top-Ausfälle der Haustechnik über ein Zeitfenster). Aggregat aus
 * facility_meldungen — KEIN persistiertes Modell. `hatVorsorge` markiert, ob für diese Störquelle bereits eine
 * Notfallvorsorge (Ersatzteile/Reaktionszeit/Sofortmaßnahmen) hinterlegt ist; fehlt sie, ist das die Lücke,
 * die das Modul sichtbar macht.
 */
final readonly class StoerquellenBefund
{
    public function __construct(
        public ?int $assetId,
        public string $bezeichnung,
        public ?AssetKategorie $kategorie,
        public int $anzahl,
        public int $offen,
        public int $dringend,
        public ?Carbon $letzteMeldung,
        public bool $hatVorsorge,
        public ?int $vorsorgeId,
    ) {}

    /** Häufige Störquelle ohne hinterlegte Vorsorge → akuter Handlungsbedarf. */
    public function istLuecke(): bool
    {
        return ! $this->hatVorsorge;
    }
}
