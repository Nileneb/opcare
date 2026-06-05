<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentDevice extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'bezeichnung', 'kategorie', 'hinweis', 'seit'];

    protected $casts = ['seit' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
