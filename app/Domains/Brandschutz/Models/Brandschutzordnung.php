<?php

namespace App\Domains\Brandschutz\Models;

use App\Domains\Brandschutz\Enums\BrandschutzordnungTeil;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Brandschutzordnung nach DIN 14096 als versioniertes Dokument-mit-Freigabe.
 * Ampel-Logik gespiegelt von Hygieneplan — empfohlene Revisionsfrist alle 24 Monate.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $titel
 * @property BrandschutzordnungTeil $teil
 * @property string $version
 * @property string|null $inhalt
 * @property int|null $freigegeben_von
 * @property Carbon|null $freigegeben_am
 * @property int $revision_intervall_monate
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $freigeber
 * @property-read Tenant $tenant
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereFreigegebenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereFreigegebenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereInhalt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereRevisionIntervallMonate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereTeil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzordnung whereVersion($value)
 *
 * @mixin \Eloquent
 */
class Brandschutzordnung extends BaseModel
{
    protected $table = 'brandschutzordnungen';

    protected $fillable = [
        'tenant_id', 'titel', 'teil', 'version', 'inhalt',
        'freigegeben_von', 'freigegeben_am', 'revision_intervall_monate', 'aktiv',
    ];

    protected $casts = [
        'freigegeben_am' => 'date',
        'teil' => BrandschutzordnungTeil::class,
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
