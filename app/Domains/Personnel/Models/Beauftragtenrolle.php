<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Beauftragten-/„befähigte-Person"-Rolle im Pflichten-Katalog der Einrichtung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string $name
 * @property string|null $rechtsbasis
 * @property bool $pflicht
 * @property string|null $schwelle
 * @property string $bereich
 * @property int|null $auffrischung_monate
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Beauftragtenbestellung> $bestellungen
 * @property-read int|null $bestellungen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereAuffrischungMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereBereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle wherePflicht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereRechtsbasis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereSchwelle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Beauftragtenrolle whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Beauftragtenrolle extends BaseModel
{
    protected $table = 'beauftragten_rollen';

    protected $fillable = ['tenant_id', 'key', 'name', 'rechtsbasis', 'pflicht', 'schwelle', 'bereich', 'auffrischung_monate', 'aktiv'];

    protected $casts = ['pflicht' => 'boolean', 'aktiv' => 'boolean'];

    /** @return HasMany<Beauftragtenbestellung, $this> */
    public function bestellungen(): HasMany
    {
        return $this->hasMany(Beauftragtenbestellung::class, 'beauftragten_rolle_id');
    }
}
