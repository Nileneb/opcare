<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('verknüpft Bewohner und Arzt über Pivot', function () {
    $resident = Resident::factory()->create();
    $arzt = Physician::create(['name' => 'Dr. Meier', 'fachrichtung' => 'Allgemeinmedizin']);

    $resident->physicians()->attach($arzt);

    expect($resident->physicians)->toHaveCount(1)
        ->and($resident->physicians->first()->name)->toBe('Dr. Meier');
});
