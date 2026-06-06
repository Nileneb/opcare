<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Monatlicher BtM-Abschluss (§ 13 Abs. 2 BtMVV): Soll- vs. Ist-Bestand, vom verantwortlichen Arzt geprüft
 * und mit Namenszeichen + Datum bestätigt. Nach der Sperre (gesperrt_am) read-only.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $btm_konto_id
 * @property Carbon $monat
 * @property numeric $soll_bestand
 * @property numeric $ist_bestand
 * @property string|null $differenz_notiz
 * @property string $geprueft_von
 * @property Carbon $pruef_datum
 * @property Carbon|null $gesperrt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read BtmKonto $konto
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereBtmKontoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereDifferenzNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereGeprueftVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereGesperrtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereIstBestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereMonat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss wherePruefDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereSollBestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BtmMonatsabschluss whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BtmMonatsabschluss extends BaseModel
{
    protected $table = 'btm_monatsabschluesse';

    protected $fillable = ['tenant_id', 'btm_konto_id', 'monat', 'soll_bestand', 'ist_bestand', 'differenz_notiz', 'geprueft_von', 'pruef_datum', 'gesperrt_am'];

    protected $casts = ['monat' => 'date', 'soll_bestand' => 'decimal:3', 'ist_bestand' => 'decimal:3', 'pruef_datum' => 'date', 'gesperrt_am' => 'datetime'];

    public function konto(): BelongsTo
    {
        return $this->belongsTo(BtmKonto::class, 'btm_konto_id');
    }

    public function differenz(): float
    {
        return round((float) $this->ist_bestand - (float) $this->soll_bestand, 3);
    }
}
