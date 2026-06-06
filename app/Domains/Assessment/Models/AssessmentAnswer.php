<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $assessment_id
 * @property int $instrument_item_id
 * @property int $assessment_option_id
 * @property int $punkte
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Assessment $assessment
 * @property-read InstrumentItem $instrumentItem
 * @property-read AssessmentOption $option
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereAssessmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereAssessmentOptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereInstrumentItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer wherePunkte($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentAnswer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AssessmentAnswer extends BaseModel
{
    protected $fillable = ['tenant_id', 'assessment_id', 'instrument_item_id', 'assessment_option_id', 'punkte'];

    protected $casts = ['punkte' => 'integer'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssessmentOption::class, 'assessment_option_id');
    }

    public function instrumentItem(): BelongsTo
    {
        return $this->belongsTo(InstrumentItem::class, 'instrument_item_id');
    }
}
