<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Database\Factories\SisAssessmentFactory;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\Versionable;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SisAssessment extends BaseModel
{
    use HasFactory, Versionable;

    protected $fillable = [
        'tenant_id', 'resident_id', 'created_by', 'superseded_by',
        'version', 'erstellt_am', 'status', 'eingangsfrage',
    ];

    protected $casts = ['erstellt_am' => 'date', 'version' => 'integer'];

    protected $attributes = ['version' => 1];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function topicFields(): HasMany
    {
        return $this->hasMany(SisTopicFieldEntry::class);
    }

    public function riskItems(): HasMany
    {
        return $this->hasMany(RiskItem::class);
    }

    public function evaluations(): MorphMany
    {
        return $this->morphMany(Evaluation::class, 'evaluable');
    }

    protected static function newFactory(): SisAssessmentFactory
    {
        return SisAssessmentFactory::new();
    }
}
