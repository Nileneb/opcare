<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Compliance\Enums\Pausenstatus;
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

        return round(max(0, $this->bruttoMinuten() - $this->pause_minuten) / 60, 2);
    }

    /** Brutto-Arbeitszeit (Ende − Beginn) in Minuten; Nachtschicht über Mitternacht korrekt. 0, solange sie läuft. */
    public function bruttoMinuten(): int
    {
        if ($this->ende === null) {
            return 0;
        }

        $start = CarbonImmutable::parse('2000-01-01 '.$this->beginn);
        $end = CarbonImmutable::parse('2000-01-01 '.$this->ende);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return (int) round($start->diffInMinutes($end));
    }

    /**
     * Nach § 4 ArbZG erforderliche Mindest-Pause anhand der Brutto-Arbeitszeit: > 9 h → 45 min, > 6 h → 30 min,
     * sonst keine. Bemessungsgrundlage ist die Brutto-Arbeitszeit (Pausen zählen nicht als Arbeitszeit).
     */
    public function erforderlichePauseMinuten(): int
    {
        $brutto = $this->bruttoMinuten();

        return match (true) {
            $brutto > 9 * 60 => 45,
            $brutto > 6 * 60 => 30,
            default => 0,
        };
    }

    /** § 4 ArbZG-Status der erfassten Pause (prüfbar, weil die Pause erfasst ist). */
    public function pausenStatus(): Pausenstatus
    {
        if ($this->ende === null) {
            return Pausenstatus::Laeuft;
        }

        $erforderlich = $this->erforderlichePauseMinuten();
        if ($erforderlich === 0) {
            return Pausenstatus::NichtRelevant;
        }

        return $this->pause_minuten >= $erforderlich ? Pausenstatus::Konform : Pausenstatus::Unzureichend;
    }
}
