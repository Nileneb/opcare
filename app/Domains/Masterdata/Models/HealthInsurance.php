<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $ik_nummer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereIkNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HealthInsurance whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class HealthInsurance extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'ik_nummer'];
}
