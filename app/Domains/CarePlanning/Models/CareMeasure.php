<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int|null $superseded_by
 * @property int $version
 * @property SisTopicField $themenfeld
 * @property string $beschreibung
 * @property string|null $ziel
 * @property string|null $verantwortlich
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Evaluation> $evaluations
 * @property-read int|null $evaluations_count
 * @property-read Resident $resident
 * @property-read Collection<int, MeasureSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereBeschreibung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereThemenfeld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereVerantwortlich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareMeasure whereZiel($value)
 *
 * @mixin \Eloquent
 */
class CareMeasure extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'superseded_by', 'version',
        'themenfeld', 'beschreibung', 'ziel', 'verantwortlich', 'aktiv',
    ];

    // WHY(Track B, At-Rest): Maßnahmen-Beschreibung/Ziel = sensibler Gesundheits-Freitext → verschlüsselt.
    protected $casts = ['themenfeld' => SisTopicField::class, 'aktiv' => 'boolean', 'version' => 'integer', 'beschreibung' => 'encrypted', 'ziel' => 'encrypted'];

    protected $attributes = ['version' => 1];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MeasureSchedule::class);
    }

    public function evaluations(): MorphMany
    {
        return $this->morphMany(Evaluation::class, 'evaluable');
    }
}
