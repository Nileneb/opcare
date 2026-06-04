<?php

namespace App\Domains\Medication\Models;

use App\Domains\Medication\Database\Factories\TradeFormFactory;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradeForm extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'einheit', 'teilbar'];

    protected $casts = ['teilbar' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(MedProduct::class);
    }

    protected static function newFactory(): TradeFormFactory
    {
        return TradeFormFactory::new();
    }
}
