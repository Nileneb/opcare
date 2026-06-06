<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $care_measure_id
 * @property string $turnus_typ
 * @property array<array-key, mixed> $turnus_daten
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read CareMeasure $careMeasure
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereCareMeasureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereTurnusDaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereTurnusTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeasureSchedule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class MeasureSchedule extends BaseModel
{
    protected $fillable = ['tenant_id', 'care_measure_id', 'turnus_typ', 'turnus_daten'];

    protected $casts = ['turnus_daten' => 'array'];

    public function careMeasure(): BelongsTo
    {
        return $this->belongsTo(CareMeasure::class);
    }
}
