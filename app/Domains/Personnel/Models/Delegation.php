<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Delegation einer Tätigkeit an eine durchführende Person (Anordnungs- vs. Durchführungsverantwortung).
 *
 * Generisch über Domänen: Pflege (Arzt→Pflegekraft, Bezug Bewohner) und Haustechnik (Betreiber→befähigte
 * Person, Bezug Anlage). Gültig, solange nicht widerrufen und die Befristung nicht abgelaufen ist.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $taetigkeit_id
 * @property int $nehmer_id
 * @property string $anordner_name
 * @property string|null $bezug_type
 * @property int|null $bezug_id
 * @property Carbon $delegiert_am
 * @property Carbon|null $gueltig_bis
 * @property string|null $nachweis_notiz
 * @property Carbon|null $widerruf_am
 * @property string|null $widerruf_grund
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Model|\Eloquent|null $bezug
 * @property-read User $nehmer
 * @property-read Taetigkeit $taetigkeit
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereAnordnerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereBezugId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereBezugType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereDelegiertAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereGueltigBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereNachweisNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereNehmerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereTaetigkeitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereWiderrufAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Delegation whereWiderrufGrund($value)
 *
 * @mixin \Eloquent
 */
class Delegation extends BaseModel
{
    protected $table = 'delegationen';

    protected $fillable = ['tenant_id', 'taetigkeit_id', 'nehmer_id', 'anordner_name', 'bezug_type', 'bezug_id', 'delegiert_am', 'gueltig_bis', 'nachweis_notiz', 'widerruf_am', 'widerruf_grund'];

    protected $casts = ['delegiert_am' => 'date', 'gueltig_bis' => 'date', 'widerruf_am' => 'datetime'];

    public function taetigkeit(): BelongsTo
    {
        return $this->belongsTo(Taetigkeit::class);
    }

    public function nehmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nehmer_id');
    }

    public function bezug(): MorphTo
    {
        return $this->morphTo();
    }

    public function aktiv(): bool
    {
        return $this->widerruf_am === null && ($this->gueltig_bis === null || $this->gueltig_bis->isFuture() || $this->gueltig_bis->isToday());
    }

    public function ampel(): string
    {
        if (! $this->aktiv()) {
            return 'gray';
        }

        return $this->gueltig_bis !== null && $this->gueltig_bis->lessThanOrEqualTo(today()->addDays(30)) ? 'amber' : 'green';
    }
}
