<?php

namespace App\Domains\Medication\Models;

use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
