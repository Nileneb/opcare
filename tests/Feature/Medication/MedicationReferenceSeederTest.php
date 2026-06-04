<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Database\Seeders\MedicationReferenceSeeder;
use App\Domains\Medication\Models\Situation;
use App\Domains\Medication\Models\TradeForm;
use App\Domains\Medication\Support\MedicationReferenceData;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet Darreichungsformen und Bedarf-Anlässe für den Mandanten — idempotent', function () {
    $this->seed(MedicationReferenceSeeder::class);
    $this->seed(MedicationReferenceSeeder::class); // zweiter Lauf darf nicht duplizieren

    expect(TradeForm::count())->toBe(count(MedicationReferenceData::tradeForms()))
        ->and(Situation::count())->toBe(count(MedicationReferenceData::situations()))
        ->and(TradeForm::where('name', 'Tablette')->where('teilbar', true)->exists())->toBeTrue();
});

it('isoliert die Stammdaten je Mandant', function () {
    $this->seed(MedicationReferenceSeeder::class);
    $countA = TradeForm::count();

    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($b);
    expect(TradeForm::count())->toBe(0); // Mandant B hat noch keine
});
