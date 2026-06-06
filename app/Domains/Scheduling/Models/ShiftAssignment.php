<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $shift_id
 * @property Carbon $dienst_am
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $auto_generiert
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Shift $shift
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereAutoGeneriert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereDienstAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftAssignment whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ShiftAssignment extends BaseModel
{
    protected $fillable = ['tenant_id', 'user_id', 'shift_id', 'dienst_am', 'notiz', 'auto_generiert'];

    protected $casts = ['dienst_am' => 'date', 'auto_generiert' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
