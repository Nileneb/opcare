<?php

namespace App\Domains\Medication\Models;

use App\Domains\Medication\Enums\StockTransactionType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedStockTransaction extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'med_stock_id', 'administration_id',
        'typ', 'menge', 'gebucht_am', 'gebucht_von',
    ];

    protected $casts = [
        'typ' => StockTransactionType::class,
        'menge' => 'decimal:3',
        'gebucht_am' => 'datetime',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(MedStock::class, 'med_stock_id');
    }
}
