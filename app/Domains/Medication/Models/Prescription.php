<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int|null $med_product_id
 * @property string|null $bhp_text
 * @property int|null $physician_id
 * @property int|null $situation_id
 * @property bool $bei_bedarf
 * @property Carbon $gueltig_von
 * @property Carbon|null $gueltig_bis
 * @property Carbon|null $abgesetzt_am
 * @property int|null $abgesetzt_von
 * @property int $created_by
 * @property string|null $hinweis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read bool $ist_aktiv
 * @property-read MedProduct|null $medProduct
 * @property-read Physician|null $physician
 * @property-read Resident $resident
 * @property-read Collection<int, PrescriptionSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read Situation|null $situation
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription aktiv()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereAbgesetztAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereAbgesetztVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereBeiBedarf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereBhpText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereGueltigBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereGueltigVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereHinweis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereMedProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription wherePhysicianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereSituationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Prescription whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Prescription extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'med_product_id', 'bhp_text', 'physician_id', 'situation_id',
        'bei_bedarf', 'gueltig_von', 'gueltig_bis', 'abgesetzt_am', 'abgesetzt_von', 'created_by', 'hinweis',
    ];

    protected $casts = [
        'bei_bedarf' => 'boolean',
        'gueltig_von' => 'date',
        'gueltig_bis' => 'date',
        'abgesetzt_am' => 'date',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function medProduct(): BelongsTo
    {
        return $this->belongsTo(MedProduct::class);
    }

    public function physician(): BelongsTo
    {
        return $this->belongsTo(Physician::class);
    }

    public function situation(): BelongsTo
    {
        return $this->belongsTo(Situation::class);
    }

    /** @return HasMany<PrescriptionSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(PrescriptionSchedule::class);
    }

    public function getIstAktivAttribute(): bool
    {
        return $this->abgesetzt_am === null
            && ($this->gueltig_bis === null || $this->gueltig_bis->isFuture() || $this->gueltig_bis->isToday());
    }

    public function scopeAktiv($q)
    {
        return $q->whereNull('abgesetzt_am');
    }
}
