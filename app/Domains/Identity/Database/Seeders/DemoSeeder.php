<?php

namespace App\Domains\Identity\Database\Seeders;

use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create(['name' => 'Haus Sonnenschein', 'slug' => 'haus-sonnenschein']);
        app(CurrentTenant::class)->set($tenant);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@opcare.local',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole('admin');

        $b = Building::create(['name' => 'Haupthaus']);
        $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
        $s = Station::create(['floor_id' => $f->id, 'name' => 'Wohnbereich 1']);
        $room = Room::create(['station_id' => $s->id, 'nummer' => '101', 'betten' => 2]);

        Resident::factory()->count(5)->create(['room_id' => $room->id]);

        $resident = Resident::query()->first();
        app(CreateSisAssessment::class)->handle(new SisAssessmentData(
            resident_id: $resident->id,
            created_by: $admin->id,
            erstellt_am: now()->format('Y-m-d'),
            eingangsfrage: 'Möchte so selbständig wie möglich bleiben.',
            themenfelder: [
                ['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.', 'strukturdaten' => null],
                ['themenfeld' => 'selbstversorgung', 'freitext' => 'Braucht Hilfe beim Waschen.', 'strukturdaten' => null],
            ],
        ));
    }
}
