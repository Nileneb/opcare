<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Tätigkeit der Berechtigungsmatrix: Mindestqualifikation, erforderliche Zusatzkompetenz, Vorbehalt
 * (§ 4 PflBG) und ob eine ärztliche Anordnung/Delegation nötig ist.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property string $label
 * @property string $bereich
 * @property bool $nur_fachkraft
 * @property bool $vorbehaltsaufgabe
 * @property int|null $erforderliche_kompetenz_id
 * @property bool $arzt_anordnung_noetig
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Kompetenz|null $erforderlicheKompetenz
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereArztAnordnungNoetig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereBereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereErforderlicheKompetenzId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereNurFachkraft($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Taetigkeit whereVorbehaltsaufgabe($value)
 *
 * @mixin \Eloquent
 */
class Taetigkeit extends BaseModel
{
    protected $table = 'taetigkeiten';

    protected $fillable = ['tenant_id', 'key', 'label', 'bereich', 'nur_fachkraft', 'vorbehaltsaufgabe', 'erforderliche_kompetenz_id', 'kompetenz_auch_fuer_fachkraft', 'arzt_anordnung_noetig', 'aktiv'];

    protected $casts = ['nur_fachkraft' => 'boolean', 'vorbehaltsaufgabe' => 'boolean', 'kompetenz_auch_fuer_fachkraft' => 'boolean', 'arzt_anordnung_noetig' => 'boolean', 'aktiv' => 'boolean'];

    public function erforderlicheKompetenz(): BelongsTo
    {
        return $this->belongsTo(Kompetenz::class, 'erforderliche_kompetenz_id');
    }
}
