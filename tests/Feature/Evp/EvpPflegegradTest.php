<?php

use App\Domains\Fhir\Evp\EvpPflegegradMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('mappt den Pflegegrad eines Bewohners auf eine EVP-Pflegegrad-Observation', function () {
    $resident = Resident::factory()->create(['pflegegrad' => 3]);

    $o = (new EvpPflegegradMapper)->map($resident);

    expect($o['resourceType'])->toBe('Observation')
        ->and($o['meta']['profile'][0])->toBe(EvpPflegegradMapper::PROFILE)
        ->and($o['status'])->toBe('final')
        ->and($o['code']['coding'][0]['code'])->toBe('80391-6')
        ->and($o['subject']['reference'])->toContain('Patient/')
        // effectivePeriod ist Pflicht (min 1) im EVP-Profil
        ->and($o['effectivePeriod']['start'])->not->toBeEmpty()
        // Pflegegrad 3 → OPS 9-984.8 aus ValueSet pflegegrad-de
        ->and($o['valueCodeableConcept']['coding'][0]['code'])->toBe('9-984.8');
});

it('lässt den value bei fehlendem Pflegegrad weg (value optional, Ressource bleibt valide)', function () {
    $resident = Resident::factory()->create(['pflegegrad' => null]);

    $o = (new EvpPflegegradMapper)->map($resident);

    expect($o)->not->toHaveKey('valueCodeableConcept')
        ->and($o['effectivePeriod']['start'])->not->toBeEmpty();
});
