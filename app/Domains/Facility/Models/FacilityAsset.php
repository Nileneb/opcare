<?php

namespace App\Domains\Facility\Models;

use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Instand zu haltendes Betriebsmittel (DIN 31051). Aus Prüfintervall + letzter Prüfung ergibt sich die
 * nächste Fälligkeit; daraus „fällig"/„überfällig" für den Wartungsplan. Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property AssetKategorie $kategorie
 * @property string|null $standort
 * @property string|null $norm
 * @property int|null $pruefintervall_monate
 * @property Carbon|null $letzte_pruefung
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, FacilityMeldung> $meldungen
 * @property-read int|null $meldungen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereKategorie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereLetztePruefung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereNorm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset wherePruefintervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereStandort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityAsset whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FacilityAsset extends BaseModel
{
    protected $fillable = ['tenant_id', 'bezeichnung', 'kategorie', 'standort', 'norm', 'pruefintervall_monate', 'letzte_pruefung', 'aktiv'];

    protected $casts = [
        'kategorie' => AssetKategorie::class,
        'letzte_pruefung' => 'date',
        'aktiv' => 'boolean',
    ];

    public function meldungen(): HasMany
    {
        return $this->hasMany(FacilityMeldung::class, 'asset_id');
    }

    public function naechstePruefung(): ?Carbon
    {
        if ($this->pruefintervall_monate === null || $this->letzte_pruefung === null) {
            return null;
        }

        return $this->letzte_pruefung->copy()->addMonths($this->pruefintervall_monate);
    }

    public function ueberfaellig(): bool
    {
        $faellig = $this->naechstePruefung();

        return $faellig !== null && $faellig->isPast();
    }

    /** Fällig innerhalb der nächsten 30 Tage (oder bereits überfällig). */
    public function faelligBald(): bool
    {
        $faellig = $this->naechstePruefung();

        return $faellig !== null && $faellig->lte(now()->addDays(30));
    }
}
