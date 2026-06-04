<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends BaseModel
{
    protected $fillable = ['tenant_id', 'building_id', 'name'];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }
}
