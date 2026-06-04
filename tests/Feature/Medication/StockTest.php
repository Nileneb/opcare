<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\StockStatus;
use App\Domains\Medication\Models\MedProduct;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('bucht einen Bestandszugang und legt Inventar + Charge an', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();

    $stock = app(AddStock::class)->handle(new StockData(
        resident_id: $resident->id, med_product_id: $product->id, menge: 100, einheit: 'Stück',
    ));

    expect((float) $stock->menge_aktuell)->toBe(100.0)
        ->and($stock->status)->toBe(StockStatus::Vorraetig)
        ->and($stock->transactions)->toHaveCount(1); // Zugangs-Buchung
});
