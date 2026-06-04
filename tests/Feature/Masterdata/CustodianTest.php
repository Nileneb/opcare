<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen gesetzlichen Betreuer für einen Bewohner an', function () {
    $resident = Resident::factory()->create();
    $c = Custodian::create([
        'resident_id' => $resident->id,
        'name' => 'RA Schmidt',
        'umfang' => 'Gesundheitsfürsorge',
        'kontakt' => 'schmidt@kanzlei.de',
    ]);

    expect($resident->custodians)->toHaveCount(1)
        ->and($c->name)->toBe('RA Schmidt');
});
