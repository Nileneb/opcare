<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $building_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Building $building
 * @property-read Collection<int, Station> $stations
 * @property-read int|null $stations_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereBuildingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Floor whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Floor extends BaseModel
{
    protected $fillable = ['tenant_id', 'building_id', 'name'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }
}
