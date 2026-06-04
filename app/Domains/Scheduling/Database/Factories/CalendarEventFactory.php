<?php

namespace App\Domains\Scheduling\Database\Factories;

use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        return [
            'type' => CalendarEventType::Arzttermin,
            'titel' => 'Arzttermin',
            'beginnt_am' => now()->addDay()->setTime(10, 0),
            'endet_am' => now()->addDay()->setTime(10, 30),
            'ganztaegig' => false,
            'created_by' => 1,
        ];
    }
}
