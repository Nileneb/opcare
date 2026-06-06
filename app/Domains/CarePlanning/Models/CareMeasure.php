<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
