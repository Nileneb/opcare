<?php

namespace App\Domains\Medication\Database\Factories;

use App\Domains\Medication\Models\TradeForm;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradeFormFactory extends Factory
{
    protected $model = TradeForm::class;

    public function definition(): array
    {
        return [
            'name' => 'Tablette',
            'einheit' => 'Stück',
            'teilbar' => false,
        ];
    }
}
