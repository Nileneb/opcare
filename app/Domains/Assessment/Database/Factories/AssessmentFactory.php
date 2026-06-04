<?php

namespace App\Domains\Assessment\Database\Factories;

use App\Domains\Assessment\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'durchgefuehrt_am' => now()->toDateString(),
            'created_by' => 1,
        ];
    }
}
