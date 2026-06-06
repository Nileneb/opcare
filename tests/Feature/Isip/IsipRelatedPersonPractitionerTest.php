<?php

use App\Domains\Fhir\Isip\IsipPractitionerMapper;
use App\Domains\Fhir\Isip\IsipRelatedPersonMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('mappt eine Kontaktperson auf einen ISiP-Angehoerigen (RelatedPerson)', function () {
    $resident = Resident::factory()->create();
    $contact = $resident->contacts()->create(['name' => 'Anna Schneider', 'beziehung' => 'Tochter', 'telefon' => '0201 1']);

    $rp = (new IsipRelatedPersonMapper)->map($contact);

    expect($rp['resourceType'])->toBe('RelatedPerson')
        ->and($rp['meta']['profile'][0])->toBe(IsipRelatedPersonMapper::PROFILE)
        ->and($rp['patient']['reference'])->toBe('Patient/isip-pflegeempfaenger-'.$resident->id)
        ->and($rp['name'][0]['family'])->toBe('Schneider')
        ->and($rp['name'][0]['given'])->toBe(['Anna'])
        // Pflicht-relationship (generischer v3-RoleCode + Beziehung als Freitext)
        ->and($rp['relationship'][0]['coding'][0]['code'])->toBe('FAMMEMB')
        ->and($rp['relationship'][0]['text'])->toBe('Tochter');
});

it('mappt einen Arzt auf eine ISiP-PersonImGesundheitswesen (Practitioner)', function () {
    $arzt = Physician::create(['name' => 'Dr. Walter Hausarzt', 'fachrichtung' => 'Allgemeinmedizin']);

    $p = (new IsipPractitionerMapper)->map($arzt);

    expect($p['resourceType'])->toBe('Practitioner')
        ->and($p['meta']['profile'][0])->toBe(IsipPractitionerMapper::PROFILE)
        // Pflicht-Identifier (opcare erfasst keine LANR → institutionell)
        ->and($p['identifier'][0]['value'])->toBe((string) $arzt->id)
        ->and($p['name'][0]['family'])->toBe('Hausarzt')
        ->and($p['name'][0]['given'])->toBe(['Dr.', 'Walter']);
});
