<?php

use App\Domains\Fhir\Isip\IsipEncounterMapper;
use App\Domains\Fhir\Isip\IsipOrganizationMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Haus am See', 'slug' => 'haus', 'ik_nummer' => '260123456', 'strasse' => 'Seestr.', 'hausnummer' => '7', 'plz' => '42489', 'ort' => 'Wülfrath']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('mappt einen aktiven Aufenthalt auf eine ISiP-Pflegeepisode (Encounter)', function () {
    $resident = Resident::factory()->create(['aufnahme_am' => '2025-01-09', 'entlassung_am' => null]);

    $e = (new IsipEncounterMapper)->map($resident);

    expect($e['resourceType'])->toBe('Encounter')
        ->and($e['meta']['profile'][0])->toBe(IsipEncounterMapper::PROFILE)
        ->and($e['status'])->toBe('in-progress')
        ->and($e['class']['code'])->toBe('IMP')
        // Pflicht-Identifier (Aufnahmenummer, Typ VN)
        ->and($e['identifier'][0]['type']['coding'][0]['code'])->toBe('VN')
        // Kontaktebene-Slice ist auf abteilungskontakt fixiert + ISiP-Pflegeart
        ->and($e['type'][0]['coding'][0]['code'])->toBe('abteilungskontakt')
        ->and($e['type'][1]['coding'][0]['code'])->toBe('langzeitpflege')
        ->and($e['subject']['reference'])->toContain('Patient/')
        ->and($e['period']['start'])->toBe('2025-01-09')
        ->and($e)->not->toHaveKey('period.end');
});

it('setzt finished + period.end bei entlassenem Bewohner', function () {
    $resident = Resident::factory()->create(['aufnahme_am' => '2024-01-01', 'entlassung_am' => '2025-01-01']);

    $e = (new IsipEncounterMapper)->map($resident);

    expect($e['status'])->toBe('finished')
        ->and($e['period']['end'])->toBe('2025-01-01');
});

it('mappt den Mandanten auf eine IsipOrganization (Pflegeeinrichtung)', function () {
    $o = (new IsipOrganizationMapper)->map($this->tenant);

    expect($o['resourceType'])->toBe('Organization')
        ->and($o['meta']['profile'][0])->toBe(IsipOrganizationMapper::PROFILE)
        ->and($o['identifier'][0]['system'])->toBe('http://fhir.de/sid/arge-ik/iknr')
        ->and($o['identifier'][0]['value'])->toBe('260123456')
        ->and($o['name'])->toBe('Haus am See')
        // Einrichtungsart Pflegeheim (SNOMED)
        ->and($o['type'][0]['coding'][0]['code'])->toBe('42665001')
        // Einrichtungs-Adresse (ZETA-/KIM-Absender-Vorbereitung)
        ->and($o['address'][0]['line'][0])->toBe('Seestr. 7')
        ->and($o['address'][0]['postalCode'])->toBe('42489')
        ->and($o['address'][0]['city'])->toBe('Wülfrath');
});

it('lässt die Adresse weg, wenn keine Einrichtungs-Adresse hinterlegt ist', function () {
    $bare = Tenant::create(['name' => 'Ohne Adresse', 'slug' => 'ohne', 'ik_nummer' => '260999999']);

    $o = (new IsipOrganizationMapper)->map($bare);

    expect($o)->not->toHaveKey('address');
});
