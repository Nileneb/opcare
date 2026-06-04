<?php

namespace App\Domains\Medication\Models;

use App\Domains\Medication\Enums\StockStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedStock extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'med_inventory_id', 'menge_initial', 'menge_aktuell',
        'einheit', 'charge', 'eingang_am', 'geoeffnet_am', 'verfall_am', 'status',
    ];

    protected $casts = [
        'menge_initial' => 'decimal:3',
        'menge_aktuell' => 'decimal:3',
        'eingang_am' => 'date',
        'geoeffnet_am' => 'date',
        'verfall_am' => 'date',
        'status' => StockStatus::class,
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(MedInventory::class, 'med_inventory_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MedStockTransaction::class);
    }
}
