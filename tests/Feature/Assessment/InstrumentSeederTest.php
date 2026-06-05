<?php

use App\Domains\Assessment\Database\Seeders\InstrumentSeeder;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('seedet Braden/Sturz/BESD/Barthel idempotent mit Items und Optionen', function () {
    $this->seed(InstrumentSeeder::class);
    $this->seed(InstrumentSeeder::class); // kein Duplikat

    expect(Instrument::count())->toBe(4)
        ->and(Instrument::where('risk_type', RiskType::Dekubitus->value)->exists())->toBeTrue()
        ->and(Instrument::where('risk_type', RiskType::Sturz->value)->exists())->toBeTrue()
        ->and(Instrument::where('risk_type', RiskType::Schmerz->value)->exists())->toBeTrue()
        ->and(Instrument::where('risk_type', RiskType::Mobilitaet->value)->exists())->toBeTrue();

    $braden = Instrument::where('name', 'Braden-Skala')->first();
    expect($braden->items()->count())->toBe(6)
        ->and(AssessmentOption::whereIn('instrument_item_id', $braden->items()->pluck('id'))->count())->toBeGreaterThan(0);
});

it('seedet den Barthel-Index mit LOINC-Codes je Item + Summen-Code', function () {
    $this->seed(InstrumentSeeder::class);

    $barthel = Instrument::with('items')->where('name', 'Barthel-Index')->first();
    expect($barthel->loinc)->toBe('96761-2')
        ->and($barthel->items)->toHaveCount(10)
        ->and($barthel->items->firstWhere('label', 'Essen')->loinc)->toBe('83184-2')
        ->and($barthel->items->every(fn ($i) => $i->loinc !== null))->toBeTrue();
});
