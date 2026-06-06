<?php

use App\Domains\Fhir\Isip\IsipPatientMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('mappt einen Bewohner auf eine ISiP-Pflegeempfaenger-Ressource', function () {
    $resident = Resident::factory()->create([
        'name' => 'Erika Muster', 'geschlecht' => 'w', 'geburtsdatum' => '1940-05-10',
    ]);

    $p = (new IsipPatientMapper)->map($resident);

    expect($p['resourceType'])->toBe('Patient')
        ->and($p['meta']['profile'][0])->toBe(IsipPatientMapper::PROFILE)
        // Pflicht-Identifier "Patientennummer" (type MR + Institutions-System)
        ->and($p['identifier'][0]['type']['coding'][0]['code'])->toBe('MR')
        ->and($p['identifier'][0]['system'])->toBe(IsipPatientMapper::PATIENTENNUMMER_SYSTEM)
        ->and($p['identifier'][0]['value'])->toBe((string) $resident->id)
        // name (use/family/given alle Pflicht in ISiK-Basis)
        ->and($p['name'][0]['use'])->toBe('official')
        ->and($p['name'][0]['family'])->toBe('Muster')
        ->and($p['name'][0]['given'])->toBe(['Erika'])
        ->and($p['gender'])->toBe('female')
        ->and($p['birthDate'])->toBe('1940-05-10');
});

it('setzt einen Platzhalter-Vornamen wenn kein Vorname erfasst ist (given Pflicht)', function () {
    $resident = Resident::factory()->create(['name' => 'Mononymous', 'geschlecht' => 'd']);

    $p = (new IsipPatientMapper)->map($resident);

    expect($p['name'][0]['family'])->toBe('Mononymous')
        ->and($p['name'][0]['given'])->toBe(['NN'])
        ->and($p['gender'])->toBe('other');
});
