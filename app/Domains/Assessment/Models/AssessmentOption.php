<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentOption extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_item_id', 'label', 'punkte', 'reihenfolge'];

    protected $casts = ['punkte' => 'integer', 'reihenfolge' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InstrumentItem::class, 'instrument_item_id');
    }
}
