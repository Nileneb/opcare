<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
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
 * @property int|null $prescription_schedule_id
 * @property Carbon $soll_zeitpunkt
 * @property AdministrationTimeslot $tageszeit
 * @property numeric $dosis
 * @property AdministrationStatus $status
 * @property Carbon|null $ist_zeitpunkt
 * @property int|null $quittiert_von
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read PrescriptionSchedule|null $schedule
 * @property-read Collection<int, MedStockTransaction> $stockTransactions
 * @property-read int|null $stock_transactions_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration offen()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereDosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereIstZeitpunkt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration wherePrescriptionScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereQuittiertVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereSollZeitpunkt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereTageszeit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MedicationAdministration whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MedicationAdministration extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'prescription_schedule_id', 'soll_zeitpunkt', 'tageszeit',
        'dosis', 'status', 'ist_zeitpunkt', 'quittiert_von', 'notiz',
    ];

    protected $casts = [
        'soll_zeitpunkt' => 'datetime',
        'ist_zeitpunkt' => 'datetime',
        'dosis' => 'decimal:3',
        'tageszeit' => AdministrationTimeslot::class,
        'status' => AdministrationStatus::class,
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PrescriptionSchedule::class, 'prescription_schedule_id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(MedStockTransaction::class, 'administration_id');
    }

    public function scopeOffen($q)
    {
        return $q->where('status', AdministrationStatus::Geplant->value);
    }
}
