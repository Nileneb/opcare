<?php

namespace App\Domains\Scheduling\Database\Factories;

use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => 'Frühdienst',
            'kind' => ShiftKind::Frueh,
            'beginn' => '06:00',
            'ende' => '14:00',
            'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00'],
            'aktiv' => true,
        ];
    }
}
