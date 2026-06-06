<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Database\Factories\CareEventFactory;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property QualityIndicator $indicator
 * @property Carbon $datum
 * @property Carbon|null $behoben_am
 * @property EventSeverity|null $severity
 * @property array<array-key, mixed>|null $details
 * @property int|null $reported_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \App\Domains\Quality\Database\Factories\CareEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereBehobenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereIndicator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereReportedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
