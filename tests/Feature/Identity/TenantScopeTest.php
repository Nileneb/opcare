<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;

it('filtert Queries automatisch nach aktivem Tenant', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    Building::create(['name' => 'Gebäude A']);

    app(CurrentTenant::class)->set($b);
    Building::create(['name' => 'Gebäude B']);

    expect(Building::count())->toBe(1)
        ->and(Building::first()->name)->toBe('Gebäude B')
        ->and(Building::first()->tenant_id)->toBe($b->id);
});
