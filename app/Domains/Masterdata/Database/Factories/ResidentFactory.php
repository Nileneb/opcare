<?php

namespace App\Domains\Masterdata\Database\Factories;

use App\Domains\Masterdata\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'geburtsdatum' => $this->faker->dateTimeBetween('-100 years', '-65 years')->format('Y-m-d'),
            'geschlecht' => $this->faker->randomElement(['m', 'w', 'd']),
            'pflegegrad' => $this->faker->numberBetween(1, 5),
            'aufnahme_am' => now()->subDays($this->faker->numberBetween(1, 1000))->format('Y-m-d'),
            'status' => 'aktiv',
        ];
    }
}
