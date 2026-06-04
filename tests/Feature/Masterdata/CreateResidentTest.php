<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Actions\CreateResident;
use App\Domains\Masterdata\Actions\UpdateResident;
use App\Domains\Masterdata\Data\ResidentData;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erstellt und aktualisiert einen Bewohner über Actions', function () {
    $data = new ResidentData(
        name: 'Hans Beispiel',
        geburtsdatum: '1938-03-12',
        geschlecht: 'm',
        aufnahme_am: '2026-02-01',
        pflegegrad: 2,
        status: 'aktiv',
        room_id: null,
    );

    $resident = app(CreateResident::class)->handle($data);
    expect($resident->name)->toBe('Hans Beispiel')->and($resident->pflegegrad)->toBe(2);

    $updated = app(UpdateResident::class)->handle($resident, new ResidentData(
        name: 'Hans Beispiel',
        geburtsdatum: '1938-03-12',
        geschlecht: 'm',
        aufnahme_am: '2026-02-01',
        pflegegrad: 3,
        status: 'aktiv',
        room_id: null,
    ));
    expect($updated->fresh()->pflegegrad)->toBe(3);
});
