<?php

namespace App\Domains\Medication\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedInventory extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'med_product_id'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function medProduct(): BelongsTo
    {
        return $this->belongsTo(MedProduct::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MedStock::class);
    }
}
