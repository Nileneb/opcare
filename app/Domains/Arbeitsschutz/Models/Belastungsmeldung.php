<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use App\Domains\Arbeitsschutz\Support\BelastungsAmpel;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Station;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Persistierte Schwellen-Überschreitung des Belastungs-Index (§ 6 ArbSchG Dokumentation).
 * Stationsbezogen — KEIN Personen-Score (§ 5 Abs. 3 Nr. 6 ArbSchG Mode A).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $station_id
 * @property string $wohnbereich
 * @property Belastungsstufe $stufe
 * @property int $score
 * @property array<string,string> $signale
 * @property Carbon $gemeldet_am
 * @property int|null $quittiert_von
 * @property Carbon|null $quittiert_am
 * @property int|null $schutzmassnahme_id
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Station|null $station
 * @property-read User|null $quittierer
 * @property-read Schutzmassnahme|null $schutzmassnahme
 * @property-read Tenant $tenant
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Belastungsmeldung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Belastungsmeldung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Belastungsmeldung query()
 *
 * @mixin \Eloquent
 */
class Belastungsmeldung extends BaseModel
{
    protected $table = 'belastungsmeldungen';

    protected $fillable = [
        'tenant_id',
        'station_id',
        'wohnbereich',
        'stufe',
        'score',
        'signale',
        'gemeldet_am',
        'quittiert_von',
        'quittiert_am',
        'schutzmassnahme_id',
        'notiz',
    ];

    protected $casts = [
        'stufe' => Belastungsstufe::class,
        'signale' => 'array',
        'gemeldet_am' => 'date',
        'quittiert_am' => 'date',
        'score' => 'integer',
    ];

    public function istOffen(): bool
    {
        return $this->quittiert_am === null;
    }

    public function lage(): int
    {
        return BelastungsAmpel::lageAusScore($this->score);
    }

    /** @return BelongsTo<Station, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /** @return BelongsTo<User, $this> */
    public function quittierer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quittiert_von');
    }

    /** @return BelongsTo<Schutzmassnahme, $this> */
    public function schutzmassnahme(): BelongsTo
    {
        return $this->belongsTo(Schutzmassnahme::class);
    }
}
