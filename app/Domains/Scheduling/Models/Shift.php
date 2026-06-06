<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Scheduling\Database\Factories\ShiftFactory;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property ShiftKind $kind
 * @property string $beginn
 * @property string $ende
 * @property array<array-key, mixed>|null $timeslots
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, ShiftAssignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read Tenant $tenant
 *
 * @method static \App\Domains\Scheduling\Database\Factories\ShiftFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereBeginn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereEnde($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereTimeslots($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shift whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Shift extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'kind', 'beginn', 'ende', 'timeslots', 'aktiv'];

    protected $casts = [
        'kind' => ShiftKind::class,
        'timeslots' => 'array',
        'aktiv' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    protected static function newFactory(): ShiftFactory
    {
        return ShiftFactory::new();
    }
}
