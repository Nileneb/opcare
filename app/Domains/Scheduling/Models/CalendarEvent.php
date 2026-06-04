<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Database\Factories\CalendarEventFactory;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
