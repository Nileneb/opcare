<?php

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt eine SIS in Version 1 an', function () {
    $resident = Resident::factory()->create();
    $sis = SisAssessment::create([
        'resident_id' => $resident->id,
        'created_by' => 1,
        'erstellt_am' => '2026-03-01',
        'status' => 'aktiv',
        'eingangsfrage' => 'Frau M. möchte selbständig bleiben.',
    ]);

    expect($sis->version)->toBe(1)
        ->and($sis->isSuperseded())->toBeFalse()
        ->and(SisAssessment::current()->count())->toBe(1);
});
