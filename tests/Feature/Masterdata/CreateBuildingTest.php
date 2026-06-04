<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Actions\CreateBuilding;
use App\Domains\Masterdata\Data\BuildingData;
use App\Domains\Masterdata\Models\Building;

it('erstellt ein Gebäude über die Action', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $building = app(CreateBuilding::class)->handle(new BuildingData(name: 'Neubau'));

    expect($building)->toBeInstanceOf(Building::class)
        ->and($building->name)->toBe('Neubau')
        ->and($building->tenant_id)->toBe($t->id);
});
