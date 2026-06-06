<?php

namespace App\Domains\Hygiene\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Einrichtungsspezifischer Hygieneplan (§ 23 Abs. 5 IfSG) als versioniertes Dokument-mit-Freigabe. Aus dem
 * Freigabedatum + Revisions-Intervall ergibt sich die Revisions-Ampel — ein Entwurf (nie freigegeben) gilt als
 * offen (rot).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $titel
 * @property string $version
 * @property string|null $inhalt
 * @property int|null $freigegeben_von
 * @property Carbon|null $freigegeben_am
 * @property int $revision_intervall_monate
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $freigeber
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereFreigegebenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereFreigegebenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereInhalt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereRevisionIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Hygieneplan whereVersion($value)
 *
 * @mixin \Eloquent
 */
class Hygieneplan extends BaseModel
{
    protected $table = 'hygieneplaene';

    protected $fillable = [
        'tenant_id', 'titel', 'version', 'inhalt', 'freigegeben_von', 'freigegeben_am',
        'revision_intervall_monate', 'aktiv',
    ];

    protected $casts = [
        'freigegeben_am' => 'date',
        'aktiv' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function freigeber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freigegeben_von');
    }

    public function naechsteRevision(): ?Carbon
    {
        return $this->freigegeben_am?->copy()->addMonths($this->revision_intervall_monate);
    }

    /** entwurf | ueberfaellig | faellig | aktuell */
    public function status(): string
    {
        if ($this->freigegeben_am === null) {
            return 'entwurf';
        }
        $revision = $this->naechsteRevision();
        if ($revision !== null && $revision->isPast()) {
            return 'ueberfaellig';
        }
        if ($revision !== null && $revision->lessThanOrEqualTo(today()->addDays(30))) {
            return 'faellig';
        }

        return 'aktuell';
    }

    public function ampel(): string
    {
        return match ($this->status()) {
            'entwurf', 'ueberfaellig' => 'red',
            'faellig' => 'amber',
            default => 'green',
        };
    }
}
