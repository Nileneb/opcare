<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentAllergy extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'substanz', 'typ', 'kategorie', 'kritikalitaet', 'reaktion', 'erfasst_am'];

    protected $casts = ['erfasst_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
