<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Custodian extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'name', 'umfang', 'kontakt'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
