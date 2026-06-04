<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erstellt eine Medikamenten-Verordnung', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();

    $rx = app(CreatePrescription::class)->handle(new PrescriptionData(
        resident_id: $resident->id,
        created_by: 1,
        med_product_id: $product->id,
        gueltig_von: now()->toDateString(),
    ));

    expect($rx->resident_id)->toBe($resident->id)
        ->and($rx->medProduct->is($product))->toBeTrue()
        ->and($rx->ist_aktiv)->toBeTrue();
});
