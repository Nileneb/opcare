<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Erfasste Ist-Arbeitszeit (BAG/EuGH-Erfassungspflicht). `ende === null` = laufende (eingestempelte) Buchung.
 *
 * Ist-Stunden = (Ende − Beginn) − Pause; Nachtschichten über Mitternacht werden korrekt gezählt.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property Carbon $datum
 * @property string $beginn
 * @property string|null $ende
 * @property int $pause_minuten
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereBeginn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereEnde($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung wherePauseMinuten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Zeitbuchung whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Zeitbuchung extends BaseModel
{
    protected $table = 'zeitbuchungen';

    protected $fillable = ['tenant_id', 'user_id', 'datum', 'beginn', 'ende', 'pause_minuten', 'notiz'];

    protected $casts = ['datum' => 'date', 'pause_minuten' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function laeuft(): bool
    {
        return $this->ende === null;
    }

    /** Ist-Stunden der abgeschlossenen Buchung (null, solange sie läuft). */
    public function istStunden(): ?float
    {
        if ($this->ende === null) {
            return null;
        }

        $start = CarbonImmutable::parse('2000-01-01 '.$this->beginn);
        $end = CarbonImmutable::parse('2000-01-01 '.$this->ende);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return round(max(0, $start->diffInMinutes($end) - $this->pause_minuten) / 60, 2);
    }
}
