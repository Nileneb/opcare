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
 * @property int $floor_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Floor $floor
 * @property-read Collection<int, Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereFloorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Station whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Station extends BaseModel
{
    protected $fillable = ['tenant_id', 'floor_id', 'name'];

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
