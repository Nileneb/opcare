<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen Bewohner an und ordnet ihn einem Zimmer zu', function () {
    $b = Building::create(['name' => 'H']);
    $f = Floor::create(['building_id' => $b->id, 'name' => 'EG']);
    $s = Station::create(['floor_id' => $f->id, 'name' => 'WB1']);
    $room = Room::create(['station_id' => $s->id, 'nummer' => '101']);

    $resident = Resident::create([
        'room_id' => $room->id,
        'name' => 'Erika Mustermann',
        'geburtsdatum' => '1940-05-01',
        'geschlecht' => 'w',
        'pflegegrad' => 3,
        'aufnahme_am' => '2026-01-15',
        'status' => 'aktiv',
    ]);

    expect($resident->room->is($room))->toBeTrue()
        ->and($resident->pflegegrad)->toBe(3)
        ->and($resident->geburtsdatum->format('Y-m-d'))->toBe('1940-05-01');
});
