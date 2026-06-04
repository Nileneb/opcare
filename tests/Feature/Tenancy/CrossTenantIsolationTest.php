<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

it('zeigt niemals Bewohner eines fremden Mandanten', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    Resident::factory()->count(3)->create();

    app(CurrentTenant::class)->set($b);
    Resident::factory()->count(2)->create();

    expect(Resident::count())->toBe(2);
    app(CurrentTenant::class)->set($a);
    expect(Resident::count())->toBe(3);
});
