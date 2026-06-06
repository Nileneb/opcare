<?php

namespace App\Domains\Catering\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Menüwahl: ein Bewohner hat sich für ein angebotenes Gericht entschieden.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $gericht_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Gericht $gericht
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereGerichtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Menuewahl whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Menuewahl extends BaseModel
{
    protected $table = 'menuewahlen';

    protected $fillable = ['tenant_id', 'resident_id', 'gericht_id'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function gericht(): BelongsTo
    {
        return $this->belongsTo(Gericht::class);
    }
}
