<?php

namespace App\Domains\SocialCare\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\SocialCare\Enums\Handlungsfeld;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Präventionsprogramm einer Einrichtung je Handlungsfeld (§ 5 SGB XI). Teilnahmen sind die Grundlage für
 * den Verwendungsnachweis gegenüber der Pflegekasse.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Handlungsfeld $handlungsfeld
 * @property string $titel
 * @property string|null $frequenz
 * @property string|null $verantwortlich
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Praeventionsteilnahme> $teilnahmen
 * @property-read int|null $teilnahmen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereFrequenz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereHandlungsfeld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsprogramm whereVerantwortlich($value)
 *
 * @mixin \Eloquent
 */
class Praeventionsprogramm extends BaseModel
{
    protected $table = 'praeventionsprogramme';

    protected $fillable = ['tenant_id', 'handlungsfeld', 'titel', 'frequenz', 'verantwortlich', 'aktiv'];

    protected $casts = ['handlungsfeld' => Handlungsfeld::class, 'aktiv' => 'boolean'];

    /** @return HasMany<Praeventionsteilnahme, $this> */
    public function teilnahmen(): HasMany
    {
        return $this->hasMany(Praeventionsteilnahme::class);
    }
}
