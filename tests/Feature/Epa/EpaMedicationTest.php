<?php

use App\Domains\Fhir\Epa\EpaMedicationMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\MedProduct;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('mappt ein Medikament mit PZN auf eine ePA-Medication-Ressource', function () {
    $m = MedProduct::factory()->create(['name' => 'Ramipril', 'staerke' => '5 mg', 'pzn' => '12345678']);

    $r = (new EpaMedicationMapper)->map($m);

    expect($r['resourceType'])->toBe('Medication')
        ->and($r['meta']['profile'][0])->toBe(EpaMedicationMapper::PROFILE)
        ->and($r['identifier'][0]['value'])->toHaveLength(64) // SHA-256 hex
        ->and($r['code']['text'])->toBe('Ramipril 5 mg')
        ->and($r['code']['coding'][0]['system'])->toBe('http://fhir.de/CodeSystem/ifa/pzn')
        ->and($r['code']['coding'][0]['code'])->toBe('12345678');
});

it('bleibt ohne PZN valide (Freitext-Code, eindeutiger Hash-Identifier)', function () {
    $m = MedProduct::factory()->create(['name' => 'Ibuprofen', 'staerke' => '400 mg', 'pzn' => null]);

    $r = (new EpaMedicationMapper)->map($m);

    expect($r['code'])->not->toHaveKey('coding')
        ->and($r['code']['text'])->toBe('Ibuprofen 400 mg')
        ->and($r['identifier'][0]['value'])->toHaveLength(64);
});
