<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $prescription_id
 * @property ScheduleFrequency $frequenz
 * @property int $intervall
 * @property array<array-key, mixed>|null $wochentage
 * @property array<array-key, mixed> $dosis
 * @property numeric|null $max_anzahl_taeglich
 * @property numeric|null $max_einzeldosis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, MedicationAdministration> $administrations
 * @property-read int|null $administrations_count
 * @property-read Prescription $prescription
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereDosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereFrequenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereIntervall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereMaxAnzahlTaeglich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereMaxEinzeldosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule wherePrescriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PrescriptionSchedule whereWochentage($value)
 *
 * @mixin \Eloquent
 */
class PrescriptionSchedule extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'prescription_id', 'frequenz', 'intervall',
        'wochentage', 'dosis', 'max_anzahl_taeglich', 'max_einzeldosis',
    ];

    protected $casts = [
        'frequenz' => ScheduleFrequency::class,
        'wochentage' => 'array',
        'dosis' => 'array',
        'intervall' => 'integer',
        'max_anzahl_taeglich' => 'decimal:2',
        'max_einzeldosis' => 'decimal:3',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }
}
