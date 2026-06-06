<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisTopicFieldEntry extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'themenfeld', 'freitext', 'strukturdaten'];

    protected $casts = [
        'themenfeld' => SisTopicField::class,
        // WHY(Track B, At-Rest): SIS-Narrativ (Freitext + strukturierte Themenfeld-Daten) verschlüsselt.
        'freitext' => 'encrypted',
        'strukturdaten' => 'encrypted:array',
    ];

    public function sisAssessment(): BelongsTo
    {
        return $this->belongsTo(SisAssessment::class);
    }
}
