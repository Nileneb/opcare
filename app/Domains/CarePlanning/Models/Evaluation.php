<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\ZielErreichung;
use App\Domains\Identity\Models\Tenant;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $evaluable_type
 * @property int $evaluable_id
 * @property int $created_by
 * @property int|null $superseded_by
 * @property int $version
 * @property Carbon $datum
 * @property ZielErreichung $zielerreichung
 * @property string|null $anlass
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Model|\Eloquent $evaluable
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereAnlass($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereEvaluableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereEvaluableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Evaluation whereZielerreichung($value)
 *
 * @mixin \Eloquent
 */
class Evaluation extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'evaluable_type', 'evaluable_id', 'created_by',
        'superseded_by', 'version', 'datum', 'zielerreichung', 'anlass',
    ];

    protected $casts = ['datum' => 'date', 'zielerreichung' => ZielErreichung::class, 'version' => 'integer'];

    protected $attributes = ['version' => 1];

    public function evaluable(): MorphTo
    {
        return $this->morphTo();
    }
}
