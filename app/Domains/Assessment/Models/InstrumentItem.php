<?php

namespace App\Domains\Assessment\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_id', 'label', 'hilfetext', 'reihenfolge'];

    protected $casts = ['reihenfolge' => 'integer'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssessmentOption::class)->orderBy('reihenfolge');
    }
}
