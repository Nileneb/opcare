<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property RecurrenceFreq $freq
 * @property int $intervall
 * @property array<array-key, mixed>|null $byday
 * @property Carbon|null $until
 * @property int|null $count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereByday($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereFreq($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereIntervall($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RecurrenceRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RecurrenceRule extends BaseModel
{
    protected $fillable = ['tenant_id', 'freq', 'intervall', 'byday', 'until', 'count'];

    protected $casts = [
        'freq' => RecurrenceFreq::class,
        'byday' => 'array',
        'until' => 'date',
        'intervall' => 'integer',
        'count' => 'integer',
    ];
}
