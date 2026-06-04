<?php

namespace App\Domains\CarePlanning\Database\Factories;

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class SisAssessmentFactory extends Factory
{
    protected $model = SisAssessment::class;

    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'created_by' => 1,
            'erstellt_am' => now()->format('Y-m-d'),
            'status' => 'aktiv',
            'eingangsfrage' => $this->faker->sentence(),
        ];
    }
}
