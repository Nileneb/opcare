<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Actions\AdministerMedication;
use App\Domains\Medication\Actions\RefuseMedication;
use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\MedStock;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('quittiert eine Gabe und bucht den Bestand ab', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();
    app(AddStock::class)->handle(new StockData($resident->id, $product->id, 50, 'Stück'));

    $a = MedicationAdministration::create([
        'resident_id' => $resident->id, 'soll_zeitpunkt' => now()->setTime(8, 0),
        'tageszeit' => AdministrationTimeslot::Morgens, 'dosis' => 1, 'status' => AdministrationStatus::Geplant,
    ]);

    app(AdministerMedication::class)->handle($a, new AdministerData(
        quittiert_von: $this->nurse->id, med_product_id: $product->id,
    ));

    $a->refresh();
    expect($a->status)->toBe(AdministrationStatus::Gegeben)
        ->and($a->quittiert_von)->toBe($this->nurse->id)
        ->and($a->ist_zeitpunkt)->not->toBeNull()
        ->and($a->stockTransactions)->toHaveCount(1);
});

it('bucht bei Unterbestand nur die tatsächlich verfügbare Menge und lässt keinen negativen Bestand zu', function () {
    $resident = Resident::factory()->create();
    $product = MedProduct::factory()->create();
    app(AddStock::class)->handle(new StockData($resident->id, $product->id, 0.5, 'Stück'));

    $a = MedicationAdministration::create([
        'resident_id' => $resident->id, 'soll_zeitpunkt' => now()->setTime(8, 0),
        'tageszeit' => AdministrationTimeslot::Morgens, 'dosis' => 1, 'status' => AdministrationStatus::Geplant,
    ]);

    app(AdministerMedication::class)->handle($a, new AdministerData(
        quittiert_von: $this->nurse->id, med_product_id: $product->id, dosis: 1,
    ));

    $a->refresh();
    expect($a->status)->toBe(AdministrationStatus::Gegeben);

    $tx = $a->stockTransactions->first();
    expect($tx)->not->toBeNull()
        ->and((float) $tx->menge)->toBe(-0.5);

    $stock = MedStock::first();
    expect((float) $stock->menge_aktuell)->toBe(0.0);
});

it('vermerkt eine Ablehnung ohne Bestandsabbuchung', function () {
    $resident = Resident::factory()->create();
    $a = MedicationAdministration::create([
        'resident_id' => $resident->id, 'soll_zeitpunkt' => now(), 'tageszeit' => AdministrationTimeslot::Abends,
        'dosis' => 1, 'status' => AdministrationStatus::Geplant,
    ]);

    app(RefuseMedication::class)->handle($a, $this->nurse->id, 'Bewohner lehnt ab');
    expect($a->fresh()->status)->toBe(AdministrationStatus::Abgelehnt)
        ->and($a->fresh()->notiz)->toBe('Bewohner lehnt ab');
});
