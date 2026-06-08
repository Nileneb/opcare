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
    $t = Tenant::create(['name' => 'A', 'slug' => 'a', 'ik_nummer' => '260326822', 'strasse' => 'Aprather Weg', 'hausnummer' => '20', 'plz' => '42489', 'ort' => 'Wülfrath']);
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

it('verhindert FHIR-Export fremder Mandanten (IDOR → 404 via tenant-scope)', function () {
    Role::findOrCreate('pflegefachkraft');

    // Bewohner in einem fremden Mandanten anlegen
    $fremd = Tenant::create(['name' => 'Fremdheim', 'slug' => 'fremdheim']);
    app(CurrentTenant::class)->set($fremd);
    $fremderBewohner = Resident::factory()->create(['tenant_id' => $fremd->id]);

    // Pflegefachkraft aus dem ursprünglichen Mandanten versucht den fremden Bewohner zu exportieren
    app(CurrentTenant::class)->set($this->resident->tenant);
    $user = User::factory()->create(['tenant_id' => $this->resident->tenant_id]);
    $user->assignRole('pflegefachkraft');

    $this->actingAs($user)->get(route('fhir.export', $fremderBewohner))->assertNotFound();
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
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');

    expect($resources->pluck('resourceType'))->toContain('Organization')->toContain('Practitioner')->toContain('PractitionerRole');

    // Einrichtungs-Stammdaten (IK + Adresse) aus dem Tenant fließen in die ÜLB-Organization
    $org = $resources->firstWhere('resourceType', 'Organization');
    expect($org['identifier'][0]['value'])->toBe('260326822')
        ->and($org['identifier'][0]['system'])->toBe('http://fhir.de/sid/arge-ik/iknr')
        ->and($org['address'][0]['line'][0])->toBe('Aprather Weg 20')
        ->and($org['address'][0]['postalCode'])->toBe('42489')
        ->and($org['address'][0]['city'])->toBe('Wülfrath')
        ->and($org['address'][0]['_line'][0]['extension'][1]['valueString'])->toBe('Aprather Weg');
});

it('lässt IK + Adresse in der ÜLB-Organization weg, wenn der Tenant sie nicht hat', function () {
    $bare = Tenant::create(['name' => 'Ohne', 'slug' => 'ohne-org']);
    app(CurrentTenant::class)->set($bare);
    $r = Resident::factory()->create(['tenant_id' => $bare->id]);

    $org = collect(app(FhirDocumentExporter::class)->export($r)['entry'])
        ->pluck('resource')->firstWhere('resourceType', 'Organization');

    expect($org)->not->toHaveKey('identifier')->not->toHaveKey('address');
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

it('mappt Status-Beobachtungen auf ihre ÜLB-Profile (Bewusstsein/Kontinenz/Atmung) + Ernährungs-Presence', function () {
    foreach ([['bewusstsein', '271591004', null], ['harnkontinenz', '450841000', null], ['stuhlkontinenz', '24029004', null], ['atmung', null, 'unauffällig, keine Atemnot'], ['kostform', '160670007', null]] as [$typ, $code, $text]) {
        $this->resident->statusObservations()->create(['typ' => $typ, 'wert_code' => $code, 'wert_text' => $text, 'erfasst_am' => '2026-06-01']);
    }
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);
    $resources = collect($bundle['entry'])->pluck('resource');
    $profileOf = fn (string $needle) => $resources->first(fn ($r) => isset($r['meta']['profile'][0]) && str_contains($r['meta']['profile'][0], $needle));

    $bewusstsein = $profileOf('Observation_Cognitive_Awareness');
    expect($bewusstsein)->not->toBeNull()
        ->and($bewusstsein['code']['coding'][0]['code'])->toBe('312012004')
        ->and($bewusstsein['valueCodeableConcept']['coding'][0]['code'])->toBe('271591004')
        ->and($bewusstsein['valueCodeableConcept']['coding'][0]['display'])->toBe('Fully conscious (finding)');

    expect($profileOf('Observation_Urinary_Continence_Differentiated_Assessment')['valueCodeableConcept']['coding'][0]['code'])->toBe('450841000')
        ->and($profileOf('Observation_Fecal_Continence_Differentiated_Assessment')['valueCodeableConcept']['coding'][0]['code'])->toBe('24029004')
        ->and($profileOf('Observation_Qualitative_Description_Breathing')['valueString'])->toBe('unauffällig, keine Atemnot')
        ->and($profileOf('Observation_Presence_Information_Nutrition'))->not->toBeNull();

    $titles = collect($bundle['entry'][0]['resource']['section'])->pluck('title')->all();
    expect($titles)->toContain('Orientierung / Psyche')
        ->toContain('Harnkontinenz differenzierte Einschätzung')
        ->toContain('Stuhlkontinenz differenzierte Einschätzung')
        ->toContain('Qualitative Beschreibung der Atmung')
        ->toContain('Ernährung');
});

it('mappt Atemwegszugang/Atmungsunterstützung/räumliche Isolation auf ihre ÜLB-Profile', function () {
    foreach ([
        ['atemwegszugang', '366141005'],
        ['atmungsunterstuetzung', '106048009:47429007=40617009,363713009=2667000'],
        ['raeumliche_isolation', '129125009:363589002=40174006,408730004=897016006'],
    ] as [$typ, $code]) {
        $this->resident->statusObservations()->create(['typ' => $typ, 'wert_code' => $code, 'erfasst_am' => '2026-06-01']);
    }
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);
    $resources = collect($bundle['entry'])->pluck('resource');
    $profileOf = fn (string $needle) => $resources->first(fn ($r) => isset($r['meta']['profile'][0]) && str_contains($r['meta']['profile'][0], $needle));

    $atemweg = $profileOf('Observation_Respiratory_Access');
    // WHY(ÜLB): Respiratory_Access bindet effective[x] auf Period (nicht dateTime).
    expect($atemweg['effectivePeriod']['start'])->not->toBeNull()
        ->and($atemweg)->not->toHaveKey('effectiveDateTime')
        // WHY(FHIR): Resource-id darf keinen Unterstrich enthalten — Katalog-Key wird normalisiert.
        ->and($profileOf('Observation_Isolation_Necessary')['id'])->toBe('status-raeumliche-isolation-'.$this->resident->statusObservations()->where('typ', 'raeumliche_isolation')->first()->id)
        ->and($profileOf('Observation_Respiratory_Support')['valueCodeableConcept']['coding'][0]['code'])->toBe('106048009:47429007=40617009,363713009=2667000');

    $titles = collect($bundle['entry'][0]['resource']['section'])->pluck('title')->all();
    expect($titles)->toContain('Atemwegszugang')
        ->toContain('Atmungsunterstützung')
        ->toContain('Notwendigkeit der räumlichen Isoation');
});

it('exportiert pro Status-Typ nur die jüngste Erfassung (Sektion max=1)', function () {
    $this->resident->statusObservations()->create(['typ' => 'bewusstsein', 'wert_code' => '371632003', 'erfasst_am' => '2026-01-01']);
    $this->resident->statusObservations()->create(['typ' => 'bewusstsein', 'wert_code' => '271591004', 'erfasst_am' => '2026-06-01']);
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');
    $bewusstsein = $resources->filter(fn ($r) => isset($r['meta']['profile'][0]) && str_contains($r['meta']['profile'][0], 'Observation_Cognitive_Awareness'));

    expect($bewusstsein)->toHaveCount(1)
        ->and($bewusstsein->first()['valueCodeableConcept']['coding'][0]['code'])->toBe('271591004');
});

it('mappt Hilfsmittel auf die Medizinprodukte-Sektion (Presence → DeviceUseStatement → Device)', function () {
    $this->resident->devices()->create(['bezeichnung' => 'Rollator', 'kategorie' => 'hilfsmittel', 'seit' => '2026-01-01']);
    $this->resident->devices()->create(['bezeichnung' => 'Hörgerät rechts', 'kategorie' => 'hilfsmittel', 'seit' => '2026-01-01']);
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);
    $resources = collect($bundle['entry'])->pluck('resource');

    $device = $resources->firstWhere('resourceType', 'Device');
    $statement = $resources->firstWhere('resourceType', 'DeviceUseStatement');
    $presence = $resources->first(fn ($r) => isset($r['meta']['profile'][0]) && str_contains($r['meta']['profile'][0], 'Relevant_Information_Medical_Devices'));

    expect($resources->where('resourceType', 'Device'))->toHaveCount(2)
        ->and($device['meta']['profile'][0])->toContain('KBV_PR_MIO_ULB_Device')
        ->and($device['type']['text'])->toBeIn(['Rollator', 'Hörgerät rechts'])
        ->and($device['patient']['reference'])->toBe($presence['subject']['reference'])
        ->and($statement['status'])->toBe('active')
        ->and($statement['device']['reference'])->toContain('Device/')
        ->and($presence['extension'])->toHaveCount(2)
        ->and($presence['extension'][0]['url'])->toContain('Reference_Has_Member');

    $titles = collect($bundle['entry'][0]['resource']['section'])->pluck('title')->all();
    expect($titles)->toContain('Medizinprodukte');
});

it('lässt die Medizinprodukte-Sektion weg, wenn keine Hilfsmittel erfasst sind', function () {
    $resources = collect(app(FhirDocumentExporter::class)->export($this->resident)['entry'])->pluck('resource');

    expect($resources->where('resourceType', 'Device'))->toHaveCount(0)
        ->and($resources->where('resourceType', 'DeviceUseStatement'))->toHaveCount(0);
});

it('mappt An-/Zugehörige auf RelatedPerson_Contact_Person (Patienten-Adressbuch)', function () {
    $this->resident->contacts()->create(['name' => 'Anna Schneider', 'beziehung' => 'Tochter', 'telefon' => '0201 1234567', 'benachrichtigen' => true]);
    $bundle = app(FhirDocumentExporter::class)->export($this->resident);
    $resources = collect($bundle['entry'])->pluck('resource');
    $related = $resources->firstWhere('resourceType', 'RelatedPerson');

    expect($related)->not->toBeNull()
        ->and($related['meta']['profile'][0])->toContain('RelatedPerson_Contact_Person')
        ->and($related['patient']['reference'])->toContain('Patient/')
        ->and($related['name'][0]['family'])->toBe('Schneider')
        ->and($related['name'][0]['given'])->toBe(['Anna'])
        ->and($related['relationship'][0]['text'])->toBe('Tochter')
        ->and($related['telecom'][0]['value'])->toBe('0201 1234567');

    $titles = collect($bundle['entry'][0]['resource']['section'])->pluck('title')->all();
    expect($titles)->toContain('Patienten-Adressbuch');
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
