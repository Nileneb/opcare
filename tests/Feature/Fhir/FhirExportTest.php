<?php

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\FhirDocumentExporter;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $user = User::factory()->create(['tenant_id' => $t->id]);
    $this->resident = Resident::factory()->create(['geschlecht' => 'w', 'geburtsdatum' => '1940-05-10', 'name' => 'Erika Muster']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Essentielle (primäre) Hypertonie']);
    $this->resident->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär', 'diagnostiziert_am' => '2025-01-01']);
    CareReport::create(['resident_id' => $this->resident->id, 'created_by' => $user->id, 'datum' => '2026-06-01 08:00:00', 'schicht' => 'frueh', 'text' => 'Bewohnerin wohlauf.']);
});

it('liefert ein FHIR-R4-Document-Bundle mit der Composition zuerst', function () {
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);

    expect($bundle['resourceType'])->toBe('Bundle')
        ->and($bundle['type'])->toBe('document')
        ->and($bundle['entry'][0]['resource']['resourceType'])->toBe('Composition')
        // WHY(FHIR bdl-9): Document-Bundle braucht system+value-Identifier (Validator-Regression)
        ->and($bundle['identifier']['system'])->not->toBeEmpty()
        ->and($bundle['identifier']['value'])->toStartWith('urn:uuid:');
});

it('mappt den Bewohner auf eine FHIR-Patient-Ressource', function () {
    $patient = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'Patient');

    expect($patient['gender'])->toBe('female')
        ->and($patient['birthDate'])->toBe('1940-05-10')
        ->and($patient['name'][0]['text'])->toBe('Erika Muster');
});

it('mappt Diagnosen auf Condition mit ICD-10-GM-Codesystem', function () {
    $condition = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'Condition');

    expect($condition['code']['coding'][0]['system'])->toBe('http://fhir.de/CodeSystem/bfarm/icd-10-gm')
        ->and($condition['code']['coding'][0]['code'])->toBe('I10');
});

it('hält alle internen Referenzen auflösbar (subject → Patient-fullUrl)', function () {
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);
    $fullUrls = collect($bundle['entry'])->pluck('fullUrl')->all();

    collect($bundle['entry'])->pluck('resource')
        ->filter(fn ($r) => isset($r['subject']['reference']))
        ->each(fn ($r) => expect($fullUrls)->toContain($r['subject']['reference']));
});

it('liefert das Bundle über die Download-Route an Leitungsrollen', function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $user = User::factory()->create(['tenant_id' => $this->resident->tenant_id]);
    $user->assignRole('pflegefachkraft');

    $this->actingAs($user)->get(route('fhir.export', $this->resident))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/fhir+json; charset=utf-8');
});

it('verwehrt Leserecht den FHIR-Download (DSGVO-Guard)', function () {
    Role::findOrCreate('leserecht');
    $user = User::factory()->create(['tenant_id' => $this->resident->tenant_id]);
    $user->assignRole('leserecht');

    $this->actingAs($user)->get(route('fhir.export', $this->resident))->assertForbidden();
});

it('erzeugt eine Composition mit XHTML-Narrativ', function () {
    $composition = app(FhirDocumentExporter::class)->export($this->resident)['entry'][0]['resource'];

    expect($composition['status'])->toBe('final')
        ->and($composition['title'])->toBe('Pflegebericht')
        ->and($composition['section'][0]['text']['div'])->toContain('http://www.w3.org/1999/xhtml')
        ->and($composition['section'][0]['text']['div'])->toContain('Bewohnerin wohlauf.');
});
