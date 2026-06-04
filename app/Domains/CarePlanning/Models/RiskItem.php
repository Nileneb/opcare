<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\RiskType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'risiko', 'eingeschaetzt', 'begruendung'];

    protected $casts = ['risiko' => RiskType::class, 'eingeschaetzt' => 'boolean'];

    public function sisAssessment(): BelongsTo
    {
        return $this->belongsTo(SisAssessment::class);
    }
}
