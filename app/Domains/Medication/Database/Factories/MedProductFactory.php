<?php

namespace App\Domains\Medication\Database\Factories;

use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedProductFactory extends Factory
{
    protected $model = MedProduct::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'wirkstoff' => $this->faker->word(),
            'staerke' => $this->faker->randomElement(['5 mg', '10 mg', '25 mg', '50 mg']),
            'btm' => false,
            'trade_form_id' => TradeForm::factory(),
        ];
    }
}
