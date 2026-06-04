<?php

namespace App\Domains\Medication\Models;

use App\Domains\Medication\Database\Factories\MedProductFactory;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedProduct extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'trade_form_id', 'name', 'wirkstoff', 'staerke', 'atc_code', 'pzn', 'btm'];

    protected $casts = ['btm' => 'boolean'];

    public function tradeForm(): BelongsTo
    {
        return $this->belongsTo(TradeForm::class);
    }

    protected static function newFactory(): MedProductFactory
    {
        return MedProductFactory::new();
    }
}
