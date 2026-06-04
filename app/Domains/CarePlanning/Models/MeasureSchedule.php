<?php

namespace App\Domains\CarePlanning\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasureSchedule extends BaseModel
{
    protected $fillable = ['tenant_id', 'care_measure_id', 'turnus_typ', 'turnus_daten'];

    protected $casts = ['turnus_daten' => 'array'];

    public function careMeasure(): BelongsTo
    {
        return $this->belongsTo(CareMeasure::class);
    }
}
