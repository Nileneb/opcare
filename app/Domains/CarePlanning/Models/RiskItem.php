<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\RiskType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'risiko', 'eingeschaetzt', 'begruendung'];

    // WHY(Track B, At-Rest): Risiko-Begründung ist sensibler Gesundheits-Freitext → verschlüsselt.
    protected $casts = ['risiko' => RiskType::class, 'eingeschaetzt' => 'boolean', 'begruendung' => 'encrypted'];

    public function sisAssessment(): BelongsTo
    {
        return $this->belongsTo(SisAssessment::class);
    }
}
