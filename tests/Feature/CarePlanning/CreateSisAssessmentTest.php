<?php

use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt eine SIS mit Themenfeldern über die Action', function () {
    $resident = Resident::factory()->create();

    $sis = app(CreateSisAssessment::class)->handle(new SisAssessmentData(
        resident_id: $resident->id,
        created_by: 1,
        erstellt_am: '2026-03-01',
        eingangsfrage: 'Möchte mobil bleiben.',
        themenfelder: [
            ['themenfeld' => 'mobilitaet', 'freitext' => 'Rollator', 'strukturdaten' => null],
        ],
    ));

    expect($sis->version)->toBe(1)
        ->and($sis->status)->toBe('aktiv')
        ->and($sis->topicFields)->toHaveCount(1);
});
