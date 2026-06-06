<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Assessment\Database\Factories\AssessmentFactory;
use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $instrument_id
 * @property int|null $score
 * @property RiskBand|null $risk_band
 * @property Carbon $durchgefuehrt_am
 * @property Carbon|null $faellig_am
 * @property string|null $notiz
 * @property int $version
 * @property int|null $superseded_by
 * @property string $status
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, AssessmentAnswer> $answers
 * @property-read int|null $answers_count
 * @property-read Instrument $instrument
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment current()
 * @method static \App\Domains\Assessment\Database\Factories\AssessmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereDurchgefuehrtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereFaelligAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereInstrumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereRiskBand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assessment whereVersion($value)
 *
 * @mixin \Eloquent
 */
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
        // WHY(Track B, At-Rest): Assessment-Notiz = sensibler Gesundheits-Freitext → verschlüsselt.
        'notiz' => 'encrypted',
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
