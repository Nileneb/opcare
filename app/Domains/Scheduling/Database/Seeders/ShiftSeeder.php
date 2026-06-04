<?php

namespace App\Domains\Scheduling\Database\Seeders;

use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    // Standard-Schichtmodell; Slot-Uhrzeiten spiegeln die bisherigen config/medication-Defaults.
    public function run(): void
    {
        $defaults = [
            ['name' => 'Frühdienst', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
                'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00']],
            ['name' => 'Spätdienst', 'kind' => ShiftKind::Spaet, 'beginn' => '14:00', 'ende' => '22:00',
                'timeslots' => ['nachmittags' => '15:00', 'abends' => '18:00', 'nacht_ab' => '22:00']],
            ['name' => 'Nachtdienst', 'kind' => ShiftKind::Nacht, 'beginn' => '22:00', 'ende' => '06:00',
                'timeslots' => []],
        ];

        foreach ($defaults as $row) {
            Shift::firstOrCreate(['name' => $row['name']], $row);
        }
    }
}
