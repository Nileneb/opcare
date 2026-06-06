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
 * Anfrage, einen Dienst abzugeben/zu tauschen (typ=tausch) oder eine krankheitsbedingte Vertretung zu suchen
 * (typ=krankheit). Wird sie übernommen, geht die Zuweisung auf die übernehmende Person über.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $shift_assignment_id
 * @property int $requested_by
 * @property string $typ
 * @property string $status
 * @property int|null $uebernommen_von
 * @property string|null $grund
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User $anfrager
 * @property-read ShiftAssignment $assignment
 * @property-read Tenant $tenant
 * @property-read User|null $uebernehmer
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereGrund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereRequestedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereShiftAssignmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereUebernommenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ShiftSwapRequest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ShiftSwapRequest extends BaseModel
{
    protected $table = 'shift_swap_requests';

    protected $fillable = ['tenant_id', 'shift_assignment_id', 'requested_by', 'typ', 'status', 'uebernommen_von', 'grund'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function anfrager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function uebernehmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uebernommen_von');
    }

    public function offen(): bool
    {
        return $this->status === 'offen';
    }
}
