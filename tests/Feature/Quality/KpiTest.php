<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Domains\Quality\Services\IndicatorService;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('liefert Pflegegrad-Verteilung und Belegung', function () {
    $b = Building::create(['name' => 'H']);
    $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
    $s = Station::create(['floor_id' => $f->id, 'name' => 'WB1']);
    $room = Room::create(['station_id' => $s->id, 'nummer' => '1', 'betten' => 4]);
    Resident::factory()->count(3)->create(['room_id' => $room->id, 'pflegegrad' => 3, 'status' => 'aktiv']);

    $kpi = app(IndicatorService::class)->kpis();
    expect($kpi->pflegegradVerteilung[3])->toBe(3)
        ->and($kpi->betten)->toBe(4)
        ->and($kpi->belegt)->toBe(3);
});
