<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('bildet die Hierarchie Gebäude→Etage→Station→Zimmer ab', function () {
    $building = Building::create(['name' => 'Haupthaus']);
    $floor = Floor::create(['building_id' => $building->id, 'name' => 'EG']);
    $station = Station::create(['floor_id' => $floor->id, 'name' => 'Wohnbereich 1']);
    $room = Room::create(['station_id' => $station->id, 'nummer' => '101', 'betten' => 2]);

    expect($room->station->floor->building->is($building))->toBeTrue()
        ->and($building->floors)->toHaveCount(1)
        ->and($room->betten)->toBe(2);
});
