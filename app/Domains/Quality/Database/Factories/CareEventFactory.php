<?php

namespace App\Domains\Quality\Database\Factories;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CareEventFactory extends Factory
{
    protected $model = CareEvent::class;

    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'indicator' => $this->faker->randomElement(QualityIndicator::cases())->value,
            'datum' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'severity' => $this->faker->randomElement(EventSeverity::cases())->value,
        ];
    }
}
