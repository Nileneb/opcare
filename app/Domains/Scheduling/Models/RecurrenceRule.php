<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Support\Models\BaseModel;

class RecurrenceRule extends BaseModel
{
    protected $fillable = ['tenant_id', 'freq', 'intervall', 'byday', 'until', 'count'];

    protected $casts = [
        'freq' => RecurrenceFreq::class,
        'byday' => 'array',
        'until' => 'date',
        'intervall' => 'integer',
        'count' => 'integer',
    ];
}
