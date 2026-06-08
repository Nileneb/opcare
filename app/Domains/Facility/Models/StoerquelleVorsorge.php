<?php

namespace App\Domains\Facility\Models;

use App\Domains\Facility\Enums\AssetKategorie;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notfallvorsorge für eine wiederkehrende Störquelle der Haustechnik (z. B. Aufzug, Rufanlage, Heizung):
 * Mindest-Ersatzteile, schriftlich fixierte Dienstleister-Reaktionszeit und interne Sofortmaßnahmen-Checkliste.
 * Greift entweder für ein konkretes Betriebsmittel (asset_id) ODER kategorieweit (asset_id null + kategorie).
 * Tenant-scoped + auditiert über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property AssetKategorie $kategorie
 * @property int|null $asset_id
 * @property string|null $mindest_ersatzteile
 * @property string|null $dienstleister
 * @property string|null $dienstleister_kontakt
 * @property string|null $reaktionszeit
 * @property int|null $reaktionszeit_stunden
 * @property array<int, string>|null $sofortmassnahmen
 * @property string|null $notiz
 * @property bool $aktiv
 * @property-read FacilityAsset|null $asset
 */
class StoerquelleVorsorge extends BaseModel
{
    protected $table = 'stoerquelle_vorsorgen';

    protected $attributes = ['aktiv' => true];

    protected $fillable = [
        'tenant_id', 'bezeichnung', 'kategorie', 'asset_id', 'mindest_ersatzteile',
        'dienstleister', 'dienstleister_kontakt', 'reaktionszeit', 'reaktionszeit_stunden',
        'sofortmassnahmen', 'notiz', 'aktiv',
    ];

    protected $casts = [
        'kategorie' => AssetKategorie::class,
        'sofortmassnahmen' => 'array',
        'aktiv' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'asset_id');
    }

    /**
     * Deckt diese Vorsorge die gegebene Störquelle ab? Ein asset-gebundenes Profil deckt nur genau dieses
     * Betriebsmittel; ein kategorieweites Profil (asset_id null) deckt jede Störquelle derselben Kategorie.
     */
    public function deckt(?int $assetId, AssetKategorie $kategorie): bool
    {
        if ($this->asset_id !== null) {
            return $this->asset_id === $assetId;
        }

        return $this->kategorie === $kategorie;
    }

    /** @return array<int, string> */
    public function sofortmassnahmenListe(): array
    {
        return array_values(array_filter(
            $this->sofortmassnahmen ?? [],
            fn ($s) => is_string($s) && trim($s) !== ''
        ));
    }
}
