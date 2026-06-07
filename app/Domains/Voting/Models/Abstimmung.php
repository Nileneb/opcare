<?php

namespace App\Domains\Voting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Quality\Models\Gremium;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Abstimmung/Wahl/Umfrage im Heim. Metadaten dürfen geloggt werden (BaseModel).
 *
 * Die eigentlichen Stimmen liegen in {@see Stimme} (kein LogsActivity, UUID-PK, keine Timestamps).
 *
 * @property Elektorat $elektorat
 * @property Stimmodus $modus
 * @property Abstimmungsart $art
 * @property AbstimmungStatus $status
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $ersteller
 * @property-read Gremium|null $gremium
 * @property-read Collection<int, AbstimmungOption> $optionen
 * @property-read int|null $optionen_count
 * @property-read Collection<int, Stimme> $stimmen
 * @property-read int|null $stimmen_count
 * @property-read Tenant|null $tenant
 * @property-read Collection<int, Wahlteilnahme> $wahlteilnahmen
 * @property-read int|null $wahlteilnahmen_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abstimmung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abstimmung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abstimmung query()
 *
 * @mixin \Eloquent
 */
class Abstimmung extends BaseModel
{
    protected $table = 'abstimmungen';

    protected $fillable = [
        'tenant_id',
        'titel',
        'beschreibung',
        'elektorat',
        'gremium_id',
        'modus',
        'art',
        'mehrfachauswahl',
        'start_am',
        'ende_am',
        'status',
        'ergebnis_sichtbar',
        'erstellt_von',
    ];

    protected $casts = [
        'elektorat' => Elektorat::class,
        'modus' => Stimmodus::class,
        'art' => Abstimmungsart::class,
        'status' => AbstimmungStatus::class,
        'mehrfachauswahl' => 'boolean',
        'ergebnis_sichtbar' => 'boolean',
        'start_am' => 'datetime',
        'ende_am' => 'datetime',
    ];

    /** @return HasMany<AbstimmungOption, $this> */
    public function optionen(): HasMany
    {
        return $this->hasMany(AbstimmungOption::class)->orderBy('sortierung');
    }

    /** @return BelongsTo<Gremium, $this> */
    public function gremium(): BelongsTo
    {
        return $this->belongsTo(Gremium::class);
    }

    /** @return HasMany<Wahlteilnahme, $this> */
    public function wahlteilnahmen(): HasMany
    {
        return $this->hasMany(Wahlteilnahme::class);
    }

    /** @return HasMany<Stimme, $this> */
    public function stimmen(): HasMany
    {
        return $this->hasMany(Stimme::class);
    }

    /** @return BelongsTo<User, $this> */
    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function offen(): bool
    {
        return $this->status === AbstimmungStatus::Offen
            && ($this->ende_am === null || $this->ende_am->gte(now()));
    }
}
