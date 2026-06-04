<?php

use App\Domains\CarePlanning\Actions\CreateCareMeasure;
use App\Domains\CarePlanning\Actions\ReviseCareMeasure;
use App\Domains\CarePlanning\Data\CareMeasureData;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt und revidiert eine Maßnahme', function () {
    $resident = Resident::factory()->create();
    $m = app(CreateCareMeasure::class)->handle(new CareMeasureData(
        resident_id: $resident->id,
        themenfeld: 'mobilitaet',
        beschreibung: 'Gehübung',
        ziel: 'Mobilität',
    ));
    expect($m->version)->toBe(1);

    $v2 = app(ReviseCareMeasure::class)->handle($m, ['beschreibung' => 'Gehübung 3x']);
    expect($v2->version)->toBe(2)
        ->and(CareMeasure::current()->count())->toBe(1)
        ->and($m->fresh()->superseded_by)->toBe($v2->id);
});
