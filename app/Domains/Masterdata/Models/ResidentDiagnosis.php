<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentDiagnosis extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'icd_code_id', 'art', 'diagnostiziert_am'];

    protected $casts = ['diagnostiziert_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function icdCode(): BelongsTo
    {
        return $this->belongsTo(IcdCode::class);
    }
}
