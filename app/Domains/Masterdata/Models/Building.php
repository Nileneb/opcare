<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends BaseModel
{
    protected $fillable = ['tenant_id', 'name'];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }
}
