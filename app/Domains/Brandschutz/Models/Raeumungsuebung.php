<?php

namespace App\Domains\Brandschutz\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Räumungs-/Evakuierungsübung (§ 10 ArbSchG / ASR A2.3) als Latest-Record-Nachweis.
 * Frist-Ampel: die jüngste Übung treibt den Status.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $durchgefuehrt_am
 * @property int|null $durchgefuehrt_von
 * @property int $intervall_monate
 * @property string|null $bereich
 * @property string|null $szenario
 * @property int|null $teilnehmer_anzahl
 * @property int|null $dauer_minuten
 * @property string|null $erkenntnisse
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $leiter
 * @property-read Tenant $tenant
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereBereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereDauerMinuten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereDurchgefuehrtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereDurchgefuehrtVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereErkenntnisse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereSzenario($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereTeilnehmerAnzahl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Raeumungsuebung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Raeumungsuebung extends BaseModel
{
    protected $table = 'raeumungsuebungen';

    protected $fillable = [
        'tenant_id', 'durchgefuehrt_am', 'durchgefuehrt_von', 'intervall_monate',
        'bereich', 'szenario', 'teilnehmer_anzahl', 'dauer_minuten', 'erkenntnisse',
    ];

    protected $casts = [
        'durchgefuehrt_am' => 'date',
        'intervall_monate' => 'integer',
        'teilnehmer_anzahl' => 'integer',
        'dauer_minuten' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function leiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'durchgefuehrt_von');
    }

    public function naechsteUebung(): Carbon
    {
        return $this->durchgefuehrt_am->copy()->addMonths($this->intervall_monate);
    }

    public function istUeberfaellig(): bool
    {
        return $this->naechsteUebung()->lt(today());
    }

    /** Frist-Ampel: 'rot' (überfällig), 'gelb' (≤30 Tage), 'gruen' (sonst). */
    public function faelligkeitsStatus(): string
    {
        $naechste = $this->naechsteUebung();

        if ($naechste->lt(today())) {
            return 'rot';
        }

        if ($naechste->lte(today()->addDays(30))) {
            return 'gelb';
        }

        return 'gruen';
    }
}
