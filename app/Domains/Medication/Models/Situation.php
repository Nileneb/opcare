<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Situation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Situation extends BaseModel
{
    protected $fillable = ['tenant_id', 'name'];
}
