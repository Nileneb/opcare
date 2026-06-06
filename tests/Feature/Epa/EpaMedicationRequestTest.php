<?php

use App\Domains\Fhir\Epa\EpaMedicationRequestMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Prescription;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
});

it('mappt eine Verordnung auf einen ePA-MedicationRequest (KVNR-Subject, ohne E-Rezept-ID)', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create(['name' => 'Ramipril', 'pzn' => '12345678']);
    $presc = Prescription::create([
        'resident_id' => $resident->id, 'med_product_id' => $product->id, 'created_by' => $this->user->id,
        'bei_bedarf' => false, 'gueltig_von' => '2025-09-06',
    ]);

    $r = (new EpaMedicationRequestMapper)->map($presc, 'X110411319');

    expect($r['resourceType'])->toBe('MedicationRequest')
        ->and($r['meta']['profile'][0])->toBe(EpaMedicationRequestMapper::PROFILE)
        ->and($r['status'])->toBe('active')
        ->and($r['intent'])->toBe('order')
        ->and($r['medicationReference']['reference'])->toContain('Medication/epa-medication-'.$product->id)
        // subject ist der KVNR-Identifier (kvid-10), nicht eine Referenz
        ->and($r['subject']['identifier']['system'])->toBe('http://fhir.de/sid/gkv/kvid-10')
        ->and($r['subject']['identifier']['value'])->toBe('X110411319')
        ->and($r['authoredOn'])->toBe('2025-09-06')
        ->and($r['dispenseRequest']['quantity']['value'])->toBe(1);
});

it('setzt status stopped bei abgesetzter Verordnung', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();
    $presc = Prescription::create([
        'resident_id' => $resident->id, 'med_product_id' => $product->id, 'created_by' => $this->user->id,
        'bei_bedarf' => false, 'gueltig_von' => '2025-01-01', 'abgesetzt_am' => '2025-06-01',
    ]);

    expect((new EpaMedicationRequestMapper)->map($presc, 'X110411319')['status'])->toBe('stopped');
});
