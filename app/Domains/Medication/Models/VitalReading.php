<?php

namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\VitalType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalReading extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'administration_id', 'typ', 'wert', 'wert2',
        'einheit', 'gemessen_am', 'gemessen_von', 'notiz',
    ];

    protected $casts = [
        'typ' => VitalType::class,
        'wert' => 'decimal:2',
        'wert2' => 'decimal:2',
        'gemessen_am' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
