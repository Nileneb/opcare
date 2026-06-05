<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\InstrumentFactory;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'name', 'loinc', 'risk_type', 'direction', 'risk_bands', 'beschreibung',
        'intervall_tage', 'version', 'superseded_by', 'status',
    ];

    protected $casts = [
        'risk_type' => RiskType::class,
        'direction' => ScaleDirection::class,
        'risk_bands' => 'array',
        'version' => 'integer',
        'intervall_tage' => 'integer',
    ];

    protected $attributes = ['version' => 1, 'status' => 'aktiv'];

    public function items(): HasMany
    {
        return $this->hasMany(InstrumentItem::class)->orderBy('reihenfolge');
    }

    protected static function newFactory(): InstrumentFactory
    {
        return InstrumentFactory::new();
    }
}
