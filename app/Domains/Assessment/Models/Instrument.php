<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\InstrumentFactory;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\Identity\Models\Tenant;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property RiskType $risk_type
 * @property ScaleDirection $direction
 * @property array<array-key, mixed> $risk_bands
 * @property string|null $beschreibung
 * @property int $intervall_tage
 * @property int $version
 * @property int|null $superseded_by
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $loinc
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, InstrumentItem> $items
 * @property-read int|null $items_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument current()
 * @method static \App\Domains\Assessment\Database\Factories\InstrumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereBeschreibung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereIntervallTage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereLoinc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereRiskBands($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereRiskType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Instrument whereVersion($value)
 *
 * @mixin \Eloquent
 */
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

    /** @return HasMany<InstrumentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InstrumentItem::class)->orderBy('reihenfolge');
    }

    protected static function newFactory(): InstrumentFactory
    {
        return InstrumentFactory::new();
    }
}
