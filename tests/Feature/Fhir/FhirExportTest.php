<?php

use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Database\Seeders\InstrumentSeeder;
use App\Domains\Assessment\Models\Instrument;
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
    $this->resident = Resident::factory()->create(['geschlecht' => 'w', 'geburtsdatum' => '1940-05-10', 'name' => 'Erika Muster', 'pflegegrad' => 3]);
    $icd = IcdCode::create(['code' => 'I10', 'bezeichnung' => 'Essentielle (primäre) Hypertonie']);
    $this->resident->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär', 'diagnostiziert_am' => '2025-01-01']);
    CareReport::create(['resident_id' => $this->resident->id, 'created_by' => $user->id, 'datum' => '2026-06-01 08:00:00', 'schicht' => 'frueh', 'text' => 'Bewohnerin wohlauf.']);
    CareMeasure::create(['resident_id' => $this->resident->id, 'themenfeld' => 'mobilitaet', 'beschreibung' => 'Gehübungen täglich', 'ziel' => 'Mobilität erhalten']);
    VitalReading::create(['resident_id' => $this->resident->id, 'typ' => VitalType::Gewicht, 'wert' => 68.5, 'einheit' => 'kg', 'gemessen_am' => '2026-06-01 07:00:00', 'gemessen_von' => $user->id]);
    $product = MedProduct::factory()->create(['name' => 'Ramipril 5 mg', 'staerke' => '5 mg']);
    $presc = Prescription::create(['resident_id' => $this->resident->id, 'med_product_id' => $product->id, 'created_by' => $user->id, 'bei_bedarf' => false, 'gueltig_von' => '2026-06-01']);
    PrescriptionSchedule::create(['prescription_id' => $presc->id, 'frequenz' => ScheduleFrequency::Taeglich, 'dosis' => ['morgens' => 1]]);
    $this->resident->allergies()->create(['substanz' => 'Penicillin', 'typ' => 'allergie', 'kategorie' => 'medikament', 'kritikalitaet' => 'hoch', 'reaktion' => 'Hautausschlag', 'erfasst_am' => '2025-05-01']);

    $this->seed(InstrumentSeeder::class);
    $barthel = Instrument::with('items.options')->where('name', 'Barthel-Index')->first();
    $answers = $barthel->items->mapWithKeys(fn ($i) => [$i->id => $i->options->first()->id])->all();
    app(ConductAssessment::class)->handle(new AssessmentInputData(
        resident_id: $this->resident->id, instrument_id: $barthel->id, created_by: $user->id, answers: $answers, durchgefuehrt_am: '2026-06-01',
    ));
});

it('liefert ein ÜLB-konformes FHIR-Document-Bundle mit der Composition zuerst', function () {
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);

    expect($bundle['resourceType'])->toBe('Bundle')
        ->and($bundle['type'])->toBe('document')
        ->and($bundle['meta']['profile'][0])->toContain('KBV_PR_MIO_ULB_Bundle')
        ->and($bundle['entry'][0]['resource']['resourceType'])->toBe('Composition')
        // WHY(FHIR bdl-9): Document-Bundle braucht system+value-Identifier (Validator-Regression)
        ->and($bundle['identifier']['system'])->not->toBeEmpty()
        ->and($bundle['identifier']['value'])->toStartWith('urn:uuid:');
});

it('mappt den Bewohner auf eine ÜLB-konforme Patient-Ressource', function () {
    $patient = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'Patient');

    expect($patient['gender'])->toBe('female')
        ->and($patient['birthDate'])->toBe('1940-05-10')
        ->and($patient['name'][0]['family'])->toBe('Muster')
        ->and($patient['name'][0]['given'])->toBe(['Erika'])
        ->and($patient['meta']['profile'][0])->toContain('KBV_PR_MIO_ULB_Patient');
});

it('erzeugt die Pflicht-Sektion pflegegrad als Care_Level-Observation', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $careLevel = $resources->first(fn ($r) => ($r['meta']['profile'][0] ?? '') === 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_Care_Level|1.0.0');

    expect($careLevel)->not->toBeNull()
        ->and($careLevel['code']['coding'][0]['code'])->toBe('80391-6')
        // Pflicht-Extension Beantragungsstatus + (bei vorhandenem Grad) OPS-Wert + Pflegegradstatus (obs-9)
        ->and($careLevel['extension'][0]['extension'][0]['url'])->toBe('antragsstatusPflegegrad')
        ->and($careLevel['valueCodeableConcept']['coding'][0]['code'])->toBe('9-984.8'); // Pflegegrad 3
});

it('mappt Diagnosen auf Condition mit ICD-10-GM, referenziert via Presence_Problems', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $condition = $resources->firstWhere('resourceType', 'Condition');
    $presence = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Presence_Problems'));

    expect($condition['code']['coding'][0]['system'])->toBe('http://fhir.de/CodeSystem/bfarm/icd-10-gm')
        ->and($condition['code']['coding'][0]['code'])->toBe('I10')
        ->and($condition['meta']['profile'][0])->toContain('Condition_Medical_Problem_Diagnosis')
        ->and($condition['verificationStatus']['coding'][0]['code'])->toBe('confirmed')
        ->and($condition)->not->toHaveKey('recordedDate')
        // Presence-Wrapper verweist via naehereInformationen-Extension auf die Condition
        ->and($presence)->not->toBeNull()
        ->and($presence['extension'][0]['valueReference']['reference'])->toContain('Condition/');
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

it('bündelt Vital-Observations in einem DiagnosticReport (vitalparameter)', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $observation = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Body_Weight'));
    $report = $resources->firstWhere('resourceType', 'DiagnosticReport');

    $loinc = collect($observation['code']['coding'])->firstWhere('system', 'http://loinc.org');
    expect($loinc['code'])->toBe('29463-7')
        ->and($observation['valueQuantity']['value'])->toBe(68.5)
        ->and($observation['meta']['profile'][0])->toContain('Observation_Body_Weight')
        ->and(collect($observation['code']['coding'])->firstWhere('system', 'http://snomed.info/sct')['code'])->toBe('27113001')
        ->and($observation['code'])->not->toHaveKey('text')
        // DiagnosticReport bündelt die konformen Vital-Observations (ÜLB-Sektion vitalparameter)
        ->and($report['meta']['profile'][0])->toContain('DiagnosticReport_Vital_Signs_and_Body_Measures')
        ->and($report['result'])->not->toBeEmpty();
});

it('mappt aktive Verordnungen auf MedicationStatement + Medication, referenziert via Information_Medicines', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $med = $resources->firstWhere('resourceType', 'MedicationStatement');
    $medication = $resources->firstWhere('resourceType', 'Medication');
    $presence = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Information_Medicines'));

    expect($med['status'])->toBe('active')
        ->and($med['dosage'][0]['text'])->toBe('morgens 1')
        ->and($med['meta']['profile'][0])->toContain('MedicationStatement_Administration_Instruction')
        ->and($med['medicationReference']['reference'])->toContain('Medication/')
        ->and($med)->not->toHaveKey('medicationCodeableConcept')
        ->and($medication['code']['text'])->toBe('Ramipril 5 mg')
        ->and($medication['meta']['profile'][0])->toContain('KBV_PR_MIO_ULB_Medication')
        ->and($presence['extension'][0]['valueReference']['reference'])->toContain('MedicationStatement/');
});

it('mappt Allergien auf AllergyIntolerance, referenziert via Presence_Allergies', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $allergy = $resources->firstWhere('resourceType', 'AllergyIntolerance');
    $presence = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Presence_Allergies'));

    expect($allergy['type'])->toBe('allergy')
        ->and($allergy['code']['text'])->toBe('Penicillin')
        ->and($allergy['patient']['reference'])->toContain('Patient/')
        ->and($allergy['meta']['profile'][0])->toContain('AllergyIntolerance')
        ->and($allergy['recorder']['reference'])->toContain('PractitionerRole/')
        ->and($allergy)->not->toHaveKey('recordedDate')
        ->and($presence['extension'][0]['valueReference']['reference'])->toContain('AllergyIntolerance/');
});

it('fügt die dokumentierende Einheit (Organization/Practitioner/PractitionerRole) hinzu', function () {
    $types = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])
        ->pluck('resource.resourceType');

    expect($types)->toContain('Organization')->toContain('Practitioner')->toContain('PractitionerRole');
});

it('mappt das Barthel-Assessment auf eine Assessment_Free-Observation (funktionsbeurteilungen)', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $free = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Assessment_Free'));
    $presence = $resources->first(fn ($r) => str_contains($r['meta']['profile'][0] ?? '', 'Observation_Presence_Functional_Assessment'));

    expect($free)->not->toBeNull()
        ->and($free['category'][0]['coding'][0]['code'])->toBe('424836000')
        ->and($free['code']['text'])->toBe('Barthel-Index')
        ->and($free['valueQuantity']['unit'])->toBe('Punkte')
        // performer muss Practitioner sein (nicht PractitionerRole)
        ->and($free['performer'][0]['reference'])->toContain('Practitioner/')
        ->and($free['performer'][0]['reference'])->not->toContain('PractitionerRole/')
        ->and($presence['extension'][0]['valueReference']['reference'])->toContain('Observation/');
});

it('mappt aktuelle Maßnahmen auf Procedure-Ressourcen (pflegerischeMassnahme)', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $procedure = $resources->firstWhere('resourceType', 'Procedure');

    expect($procedure)->not->toBeNull()
        ->and($procedure['meta']['profile'][0])->toContain('Procedure_Nursing_Measures')
        ->and($procedure['status'])->toBe('completed')
        ->and($procedure['code']['text'])->toContain('Gehübungen');
});

it('erzeugt eine ÜLB-konforme Composition mit slice-konformen Sektionen + Verlauf-Narrativ', function () {
    $composition = app(FhirDocumentExporter::class)->export($this->resident)['entry'][0]['resource'];
    $titles = collect($composition['section'])->pluck('title')->all();

    expect($composition['status'])->toBe('final')
        ->and($composition['meta']['profile'][0])->toContain('KBV_PR_MIO_ULB_Composition')
        ->and($composition['title'])->toBe('Überleitungsbogen')
        ->and($composition['type']['coding'][0]['code'])->toBe('721919000')
        ->and($titles)->toContain('Pflegegrad')
        ->toContain('Vitalzeichen und Körpermaße')
        ->toContain('Probleme')
        ->toContain('Allergien und Unverträglichkeiten')
        ->toContain('Medikationsplan')
        ->toContain('Funktionsbeurteilungen')
        ->toContain('Pflegerische Maßnahme')
        // section.text ist im ÜLB-Profil verboten → Verlauf wandert ins Composition.text-Narrativ
        ->and($composition['text']['div'])->toContain('Bewohnerin wohlauf.')
        ->and(collect($composition['section'])->every(fn ($s) => ! isset($s['text'])))->toBeTrue();
});
