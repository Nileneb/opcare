<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Lieferant einer Einrichtung — „eine Stufe zurück" gemäß Art. 18 VO (EG) 178/2002.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $anschrift
 * @property string|null $kontakt
 * @property string|null $lieferantennr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Lagerschicht> $schichten
 * @property-read int|null $schichten_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereAnschrift($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereKontakt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereLieferantennr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lieferant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Lieferant extends BaseModel
{
    protected $table = 'lieferanten';

    protected $fillable = ['tenant_id', 'name', 'anschrift', 'kontakt', 'lieferantennr'];

    /** @return HasMany<Lagerschicht, $this> */
    public function schichten(): HasMany
    {
        return $this->hasMany(Lagerschicht::class);
    }
}
