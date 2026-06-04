<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends BaseModel
{
    protected $fillable = ['tenant_id', 'station_id', 'nummer', 'betten'];

    protected $casts = ['betten' => 'integer'];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
