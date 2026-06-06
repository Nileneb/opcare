<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Database\Factories\SisAssessmentFactory;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $created_by
 * @property int|null $superseded_by
 * @property int $version
 * @property Carbon $erstellt_am
 * @property string $status
 * @property string|null $eingangsfrage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Evaluation> $evaluations
 * @property-read int|null $evaluations_count
 * @property-read Resident $resident
 * @property-read Collection<int, RiskItem> $riskItems
 * @property-read int|null $risk_items_count
 * @property-read Tenant $tenant
 * @property-read Collection<int, SisTopicFieldEntry> $topicFields
 * @property-read int|null $topic_fields_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment current()
 * @method static \App\Domains\CarePlanning\Database\Factories\SisAssessmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereEingangsfrage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereErstelltAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisAssessment whereVersion($value)
 *
 * @mixin \Eloquent
 */
class SisAssessment extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'erstellt_am', 'status', 'eingangsfrage',
    ];

    protected $casts = ['erstellt_am' => 'date', 'version' => 'integer'];

    protected $attributes = ['version' => 1];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function topicFields(): HasMany
    {
        return $this->hasMany(SisTopicFieldEntry::class);
    }

    public function riskItems(): HasMany
    {
        return $this->hasMany(RiskItem::class);
    }

    public function evaluations(): MorphMany
    {
        return $this->morphMany(Evaluation::class, 'evaluable');
    }

    protected static function newFactory(): SisAssessmentFactory
    {
        return SisAssessmentFactory::new();
    }
}
