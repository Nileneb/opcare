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
 * @property int $station_id
 * @property string $nummer
 * @property int $betten
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Resident> $residents
 * @property-read int|null $residents_count
 * @property-read Station $station
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereBetten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereStationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Room extends BaseModel
{
    protected $fillable = ['tenant_id', 'station_id', 'nummer', 'betten'];

    protected $casts = ['betten' => 'integer'];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
