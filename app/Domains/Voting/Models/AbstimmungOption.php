<?php

namespace App\Domains\Voting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Antwortoption innerhalb einer {@see Abstimmung}.
 *
 * @property-read Abstimmung|null $abstimmung
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Stimme> $stimmen
 * @property-read int|null $stimmen_count
 * @property-read Tenant|null $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AbstimmungOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AbstimmungOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AbstimmungOption query()
 *
 * @mixin \Eloquent
 */
class AbstimmungOption extends BaseModel
{
    protected $table = 'abstimmung_optionen';

    protected $fillable = [
        'tenant_id',
        'abstimmung_id',
        'text',
        'sortierung',
    ];

    /** @return BelongsTo<Abstimmung, $this> */
    public function abstimmung(): BelongsTo
    {
        return $this->belongsTo(Abstimmung::class);
    }

    /** @return HasMany<Stimme, $this> */
    public function stimmen(): HasMany
    {
        return $this->hasMany(Stimme::class, 'option_id');
    }
}
