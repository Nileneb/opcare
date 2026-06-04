<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentInsurance extends BaseModel
{
    protected $table = 'resident_insurance';

    protected $fillable = ['tenant_id', 'resident_id', 'health_insurance_id', 'versichertennr', 'ist_primaer'];

    protected $casts = ['ist_primaer' => 'boolean'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class);
    }
}
