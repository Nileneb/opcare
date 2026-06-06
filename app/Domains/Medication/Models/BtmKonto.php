<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * BtM-Bestandskonto: genau ein Bewohner + eine Substanz (§ 5c BtMVV — kein einrichtungsweiter Vorrat).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property string $substanz
 * @property string|null $form
 * @property string|null $staerke
 * @property string $einheit
 * @property string $arzt_name
 * @property Carbon $eroeffnet_am
 * @property Carbon|null $geschlossen_am
 * @property string|null $schliessgrund
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BtmMonatsabschluss> $abschluesse
 * @property-read int|null $abschluesse_count
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, BtmBuchung> $buchungen
 * @property-read int|null $buchungen_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereArztName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereEroeffnetAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereForm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereGeschlossenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereSchliessgrund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereStaerke($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereSubstanz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmKonto whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BtmKonto extends BaseModel
{
    protected $table = 'btm_konten';

    protected $fillable = ['tenant_id', 'resident_id', 'substanz', 'form', 'staerke', 'einheit', 'arzt_name', 'eroeffnet_am', 'geschlossen_am', 'schliessgrund'];

    protected $casts = ['eroeffnet_am' => 'date', 'geschlossen_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return HasMany<BtmBuchung, $this> */
    public function buchungen(): HasMany
    {
        return $this->hasMany(BtmBuchung::class)->orderBy('lfd_nr');
    }

    /** @return HasMany<BtmMonatsabschluss, $this> */
    public function abschluesse(): HasMany
    {
        return $this->hasMany(BtmMonatsabschluss::class);
    }

    public function bestand(): float
    {
        // eigene Query statt der (aufsteigend sortierten) Relation — sonst gewinnt die ASC-Sortierung.
        return (float) (BtmBuchung::where('btm_konto_id', $this->id)->orderByDesc('lfd_nr')->value('bestand_nach') ?? 0.0);
    }

    public function offen(): bool
    {
        return $this->geschlossen_am === null;
    }
}
