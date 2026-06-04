<?php

namespace App\Domains\Assessment\Database\Factories;

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstrumentFactory extends Factory
{
    protected $model = Instrument::class;

    public function definition(): array
    {
        return [
            'name' => 'Braden-Skala',
            'risk_type' => RiskType::Dekubitus,
            'direction' => ScaleDirection::LowerIsWorse,
            'risk_bands' => [
                ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
                ['band' => 'hoch', 'min' => 10, 'max' => 12],
                ['band' => 'mittel', 'min' => 13, 'max' => 14],
                ['band' => 'gering', 'min' => 15, 'max' => 18],
                ['band' => 'kein', 'min' => 19, 'max' => null],
            ],
            'intervall_tage' => 90,
        ];
    }
}
