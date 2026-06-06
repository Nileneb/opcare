<?php

use App\Domains\Fhir\Erezept\ErezeptBundleMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\HealthInsurance;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\PrescriptionSchedule;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
    $this->resident = Resident::factory()->create(['name' => 'Maria Schneider', 'geburtsdatum' => '1940-05-10']);
    $this->arzt = Physician::create(['name' => 'Dr. Walter Hausarzt', 'fachrichtung' => 'Allgemeinmedizin', 'lanr' => '838382202', 'bsnr' => '031234567']);
    $kasse = HealthInsurance::create(['name' => 'AOK', 'ik_nummer' => '104212505']);
    $this->ins = $this->resident->insurances()->create(['health_insurance_id' => $kasse->id, 'versichertennr' => 'X110411319', 'ist_primaer' => true]);
    $product = MedProduct::factory()->create(['name' => 'Ramipril', 'wirkstoff' => 'Ramipril', 'staerke' => '5 mg', 'pzn' => '06313728']);
    $this->presc = Prescription::create([
        'resident_id' => $this->resident->id, 'med_product_id' => $product->id, 'created_by' => $this->user->id,
        'bei_bedarf' => false, 'gueltig_von' => '2025-10-30',
    ]);
    PrescriptionSchedule::create(['prescription_id' => $this->presc->id, 'frequenz' => 'taeglich', 'dosis' => ['morgens' => 1, 'abends' => 1]]);
    $this->presc->load(['resident', 'medProduct', 'schedules']);
});

it('baut ein KBV-E-Rezept-Bundle (Muster 16) aus Verordnung + Arzt + Versicherung', function () {
    $b = (new ErezeptBundleMapper)->build($this->presc, $this->arzt, $this->ins);

    expect($b['resourceType'])->toBe('Bundle')
        ->and($b['type'])->toBe('document')
        ->and($b['meta']['profile'][0])->toContain('KBV_PR_ERP_Bundle|1.3');

    $byType = collect($b['entry'])->keyBy(fn ($e) => $e['resource']['resourceType']);
    expect($byType->keys())->toContain('Composition', 'MedicationRequest', 'Medication', 'Patient', 'Practitioner', 'Organization', 'Coverage');

    // Patient: KVNR; Practitioner: LANR (Titel als prefix, given max 1); Organization: BSNR
    expect($byType['Patient']['resource']['identifier'][0]['value'])->toBe('X110411319')
        ->and($byType['Practitioner']['resource']['identifier'][0]['value'])->toBe('838382202')
        ->and($byType['Practitioner']['resource']['name'][0]['given'])->toBe(['Walter'])
        ->and($byType['Practitioner']['resource']['name'][0]['prefix'])->toBe(['Dr.'])
        ->and($byType['Organization']['resource']['identifier'][0]['value'])->toBe('031234567')
        ->and($byType['Medication']['resource']['code']['coding'][0]['code'])->toBe('06313728')
        ->and($byType['MedicationRequest']['resource']['dosageInstruction'][0]['text'])->toBe('1-0-1-0');
});
