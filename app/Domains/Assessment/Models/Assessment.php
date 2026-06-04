<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\AssessmentFactory;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'instrument_id', 'score', 'risk_band',
        'durchgefuehrt_am', 'faellig_am', 'notiz', 'version', 'superseded_by', 'status', 'created_by',
    ];

    protected $casts = [
        'risk_band' => RiskBand::class,
        'score' => 'integer',
        'durchgefuehrt_am' => 'date',
        'faellig_am' => 'date',
        'version' => 'integer',
    ];

    protected $attributes = ['version' => 1, 'status' => 'aktiv'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class);
    }

    public function istFaellig(): bool
    {
        return $this->faellig_am !== null && $this->faellig_am->isToday() || ($this->faellig_am?->isPast() ?? false);
    }

    protected static function newFactory(): AssessmentFactory
    {
        return AssessmentFactory::new();
    }
}
