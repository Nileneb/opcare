<?php

namespace App\Domains\Quality\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Database\Factories\CareEventFactory;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'resident_id',
        'indicator',
        'datum',
        'behoben_am',
        'severity',
        'details',
        'reported_by',
    ];

    protected $casts = [
        'indicator' => QualityIndicator::class,
        'severity' => EventSeverity::class,
        'datum' => 'date',
        'behoben_am' => 'date',
        'details' => 'array',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    protected static function newFactory(): CareEventFactory
    {
        return CareEventFactory::new();
    }
}
