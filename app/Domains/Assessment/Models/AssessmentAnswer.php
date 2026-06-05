<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends BaseModel
{
    protected $fillable = ['tenant_id', 'assessment_id', 'instrument_item_id', 'assessment_option_id', 'punkte'];

    protected $casts = ['punkte' => 'integer'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AssessmentOption::class, 'assessment_option_id');
    }

    public function instrumentItem(): BelongsTo
    {
        return $this->belongsTo(InstrumentItem::class, 'instrument_item_id');
    }
}
