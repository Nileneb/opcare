<?php

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\FhirDocumentExporter;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\PrescriptionSchedule;
use App\Domains\Medication\Models\VitalReading;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $user = User::factory()->create(['tenant_id' => $t->id]);
    $this->resident = Resident::factory()->create(['geschlecht' => 'w', 'geburtsdatum' => '1940-05-10', 'name' => 'Erika Muster']);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Essentielle (primäre) Hypertonie']);
    $this->resident->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär', 'diagnostiziert_am' => '2025-01-01']);
    CareReport::create(['resident_id' => $this->resident->id, 'created_by' => $user->id, 'datum' => '2026-06-01 08:00:00', 'schicht' => 'frueh', 'text' => 'Bewohnerin wohlauf.']);
    CareMeasure::create(['resident_id' => $this->resident->id, 'themenfeld' => 'mobilitaet', 'beschreibung' => 'Gehübungen täglich', 'ziel' => 'Mobilität erhalten']);
    VitalReading::create(['resident_id' => $this->resident->id, 'typ' => VitalType::Gewicht, 'wert' => 68.5, 'einheit' => 'kg', 'gemessen_am' => '2026-06-01 07:00:00', 'gemessen_von' => $user->id]);
    $product = MedProduct::factory()->create(['name' => 'Ramipril 5 mg', 'staerke' => '5 mg']);
    $presc = Prescription::create(['resident_id' => $this->resident->id, 'med_product_id' => $product->id, 'created_by' => $user->id, 'bei_bedarf' => false, 'gueltig_von' => '2026-06-01']);
    PrescriptionSchedule::create(['prescription_id' => $presc->id, 'frequenz' => ScheduleFrequency::Taeglich, 'dosis' => ['morgens' => 1]]);
    $this->resident->allergies()->create(['substanz' => 'Penicillin', 'typ' => 'allergie', 'kategorie' => 'medikament', 'kritikalitaet' => 'hoch', 'reaktion' => 'Hautausschlag', 'erfasst_am' => '2025-05-01']);
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

it('mappt Vitalwerte auf Observation mit LOINC + Maßnahmen auf CarePlan', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');

    $observation = $resources->firstWhere('resourceType', 'Observation');
    $carePlan = $resources->firstWhere('resourceType', 'CarePlan');

    expect($observation['code']['coding'][0]['system'])->toBe('http://loinc.org')
        ->and($observation['code']['coding'][0]['code'])->toBe('29463-7')
        ->and($observation['valueQuantity']['value'])->toBe(68.5)
        ->and($carePlan['status'])->toBe('active')
        ->and($carePlan['activity'][0]['detail']['description'])->toContain('Gehübungen');
});

it('mappt aktive Verordnungen auf MedicationStatement', function () {
    $med = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'MedicationStatement');

    expect($med['status'])->toBe('active')
        ->and($med['medicationCodeableConcept']['text'])->toBe('Ramipril 5 mg')
        ->and($med['dosage'][0]['text'])->toBe('morgens 1');
});

it('mappt Allergien auf FHIR AllergyIntolerance', function () {
    $allergy = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'AllergyIntolerance');

    expect($allergy['type'])->toBe('allergy')
        ->and($allergy['category'])->toBe(['medication'])
        ->and($allergy['criticality'])->toBe('high')
        ->and($allergy['code']['text'])->toBe('Penicillin')
        ->and($allergy['reaction'][0]['manifestation'][0]['text'])->toBe('Hautausschlag')
        ->and($allergy['patient']['reference'])->toContain('Patient/');
});

it('erzeugt eine Composition mit referenzierten Sektionen + Verlauf-Narrativ', function () {
    $composition = app(FhirDocumentExporter::class)->export($this->resident)['entry'][0]['resource'];
    $titles = collect($composition['section'])->pluck('title')->all();

    expect($composition['status'])->toBe('final')
        ->and($titles)->toContain('Diagnosen')->toContain('Allergien')->toContain('Medikation')->toContain('Pflegeplan')->toContain('Beobachtungen / Vitalwerte')->toContain('Verlauf')
        ->and(collect($composition['section'])->firstWhere('title', 'Verlauf')['text']['div'])->toContain('Bewohnerin wohlauf.');
});
