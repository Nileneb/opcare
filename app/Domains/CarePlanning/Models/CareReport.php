<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\Shift;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $created_by
 * @property int|null $superseded_by
 * @property int $version
 * @property Carbon $datum
 * @property Shift $schicht
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereSchicht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereSupersededBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CareReport whereVersion($value)
 *
 * @mixin \Eloquent
 */
class CareReport extends BaseModel
{
    use Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'datum', 'schicht', 'text',
    ];

    // WHY(Track B, At-Rest): Pflegeverlauf ist sensibler Gesundheits-Freitext, nicht SQL-durchsucht → verschlüsselt.
    protected $casts = ['datum' => 'datetime', 'schicht' => Shift::class, 'version' => 'integer', 'text' => 'encrypted'];

    protected $attributes = ['version' => 1];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
