<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Personnel\Enums\KompetenzTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Kompetenz im Skill-Baum (Grundberuf/Weiterbildung/interne Schulung). Voraussetzungen bilden einen
 * gerichteten azyklischen Graphen (z. B. „Wundexperte ICW" setzt „Pflegefachkraft" voraus).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string $name
 * @property KompetenzTyp $typ
 * @property bool $ist_fachkraft
 * @property string|null $rechtsbasis
 * @property int|null $umfang_stunden
 * @property int|null $gueltigkeit_monate
 * @property int|null $auffrischung_monate
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read Collection<int, Kompetenz> $voraussetzungen
 * @property-read int|null $voraussetzungen_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereAuffrischungMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereGueltigkeitMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereIstFachkraft($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereRechtsbasis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereUmfangStunden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kompetenz whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Kompetenz extends BaseModel
{
    protected $table = 'kompetenzen';

    protected $fillable = ['tenant_id', 'key', 'name', 'typ', 'ist_fachkraft', 'rechtsbasis', 'umfang_stunden', 'gueltigkeit_monate', 'auffrischung_monate', 'aktiv'];

    protected $casts = ['typ' => KompetenzTyp::class, 'ist_fachkraft' => 'boolean', 'aktiv' => 'boolean'];

    /** @return BelongsToMany<Kompetenz, $this> */
    public function voraussetzungen(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'kompetenz_voraussetzungen', 'kompetenz_id', 'voraussetzung_id');
    }
}
