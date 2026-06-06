<?php

namespace App\Domains\Hygiene\Models;

use App\Domains\Hygiene\Enums\BefundArt;
use App\Domains\Hygiene\Enums\Erreger;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Erreger-/Infektions-Befund je Bewohner:in — die fortlaufende Surveillance-Liste nach § 23 Abs. 4 IfSG
 * (Aufzeichnung nosokomialer Infektionen und resistenter Erreger). Bewusst änderungsarm geführt: erfasst und
 * später aufgehoben (Sanierung/Genesung); meldepflichtige Fälle (§§ 6/7 IfSG) werden mit `gemeldet_am`
 * dokumentiert — die Meldepflicht wird nie stillschweigend übergangen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property Erreger $erreger
 * @property BefundArt $art
 * @property Carbon $festgestellt_am
 * @property Carbon|null $aufgehoben_am
 * @property string|null $massnahmen
 * @property bool $meldepflichtig
 * @property Carbon|null $gemeldet_am
 * @property int|null $erfasst_von_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $erfasser
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereAufgehobenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereErfasstVonUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereErreger($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereFestgestelltAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereGemeldetAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereMassnahmen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereMeldepflichtig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InfektionsBefund whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InfektionsBefund extends BaseModel
{
    protected $table = 'infektions_befunde';

    protected $fillable = [
        'tenant_id', 'resident_id', 'erreger', 'art', 'festgestellt_am', 'aufgehoben_am',
        'massnahmen', 'meldepflichtig', 'gemeldet_am', 'erfasst_von_user_id',
    ];

    protected $casts = [
        'erreger' => Erreger::class,
        'art' => BefundArt::class,
        'festgestellt_am' => 'date',
        'aufgehoben_am' => 'date',
        'meldepflichtig' => 'boolean',
        'gemeldet_am' => 'date',
    ];

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<User, $this> */
    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erfasst_von_user_id');
    }

    public function aktiv(): bool
    {
        return $this->aufgehoben_am === null;
    }

    /** Meldepflichtig, aber noch nicht ans Gesundheitsamt gemeldet → offene Pflicht. */
    public function meldungOffen(): bool
    {
        return $this->meldepflichtig && $this->gemeldet_am === null;
    }

    /** rot: aktiv & Meldung offen · amber: aktiv · grün: aufgehoben */
    public function ampel(): string
    {
        if (! $this->aktiv()) {
            return 'green';
        }

        return $this->meldungOffen() ? 'red' : 'amber';
    }
}
