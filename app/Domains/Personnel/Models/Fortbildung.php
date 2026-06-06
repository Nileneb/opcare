<?php

namespace App\Domains\Personnel\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Enums\FortbildungsThema;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine geplante oder absolvierte Fortbildung je Mitarbeiter:in (Fortbildungsplan, QPR QB6). Solange nicht
 * absolviert, gilt sie als geplant; bei Pflichtthemen ergibt sich aus dem Absolvier-Datum + Intervall die
 * Auffrischungs-Fälligkeit (Nachweis-mit-Frist). Das Intervall wird je Eintrag aus dem Thema vorbelegt,
 * bleibt aber überschreibbar.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property FortbildungsThema $thema
 * @property string $titel
 * @property string|null $anbieter
 * @property Carbon|null $geplant_am
 * @property Carbon|null $absolviert_am
 * @property int|null $umfang_stunden
 * @property bool $pflicht
 * @property int|null $intervall_monate
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereAbsolviertAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereAnbieter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereGeplantAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung wherePflicht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereThema($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereUmfangStunden($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Fortbildung whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Fortbildung extends BaseModel
{
    protected $table = 'fortbildungen';

    protected $fillable = [
        'tenant_id', 'user_id', 'thema', 'titel', 'anbieter', 'geplant_am', 'absolviert_am',
        'umfang_stunden', 'pflicht', 'intervall_monate', 'notiz',
    ];

    protected $casts = [
        'thema' => FortbildungsThema::class,
        'geplant_am' => 'date',
        'absolviert_am' => 'date',
        'pflicht' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intervall(): ?int
    {
        return $this->intervall_monate ?? $this->thema->intervallMonate();
    }

    public function naechsteFaelligkeit(): ?Carbon
    {
        if ($this->absolviert_am === null || ! $this->pflicht) {
            return null;
        }
        $intervall = $this->intervall();

        return $intervall === null ? null : $this->absolviert_am->copy()->addMonths($intervall);
    }

    /** geplant | ueberfaellig_geplant | absolviert | faellig | ueberfaellig | aktuell */
    public function status(): string
    {
        if ($this->absolviert_am === null) {
            if ($this->geplant_am !== null && $this->geplant_am->isPast()) {
                return 'ueberfaellig_geplant';
            }

            return 'geplant';
        }

        $faellig = $this->naechsteFaelligkeit();
        if ($faellig === null) {
            return 'absolviert';
        }
        if ($faellig->isPast()) {
            return 'ueberfaellig';
        }

        return $faellig->lessThanOrEqualTo(today()->addDays(60)) ? 'faellig' : 'aktuell';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'ueberfaellig', 'ueberfaellig_geplant' => 'red',
            'faellig' => 'amber',
            'geplant' => 'gray',
            default => 'green',
        };
    }
}
