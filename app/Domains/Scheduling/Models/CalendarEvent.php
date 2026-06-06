<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Database\Factories\CalendarEventFactory;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $resident_id
 * @property CalendarEventType $type
 * @property string $titel
 * @property string|null $beschreibung
 * @property Carbon $beginnt_am
 * @property Carbon|null $endet_am
 * @property bool $ganztaegig
 * @property int|null $recurrence_rule_id
 * @property Carbon|null $abgesagt_am
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read RecurrenceRule|null $recurrenceRule
 * @property-read Resident|null $resident
 * @property-read Tenant $tenant
 *
 * @method static \App\Domains\Scheduling\Database\Factories\CalendarEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent imZeitraum(string $von, string $bis)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereAbgesagtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereBeginntAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereBeschreibung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereEndetAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereGanztaegig($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereRecurrenceRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CalendarEvent extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'resident_id', 'type', 'titel', 'beschreibung',
        'beginnt_am', 'endet_am', 'ganztaegig', 'recurrence_rule_id', 'abgesagt_am', 'created_by',
    ];

    protected $casts = [
        'type' => CalendarEventType::class,
        'beginnt_am' => 'datetime',
        'endet_am' => 'datetime',
        'abgesagt_am' => 'datetime',
        'ganztaegig' => 'boolean',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(RecurrenceRule::class);
    }

    public function istAbgesagt(): bool
    {
        return $this->abgesagt_am !== null;
    }

    public function istWiederkehrend(): bool
    {
        return $this->recurrence_rule_id !== null;
    }

    public function scopeImZeitraum($q, string $von, string $bis)
    {
        return $q->where('beginnt_am', '<=', $bis)
            ->where(function ($q) use ($von) {
                $q->whereNull('endet_am')->orWhere('endet_am', '>=', $von);
            });
    }

    protected static function newFactory(): CalendarEventFactory
    {
        return CalendarEventFactory::new();
    }
}
