<?php

namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
