<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Services\AssemblePackages;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('baut pseudonymisierte Pakete inkl. aktiver Indikatoren am Stichtag', function () {
    $r = Resident::factory()->create(['aufnahme_am' => '2026-01-01', 'pflegegrad' => 3, 'geschlecht' => 'w']);
    $icd = IcdCode::create(['code' => 'F00.0', 'bezeichnung' => 'Demenz']);
    $r->diagnoses()->create(['icd_code_id' => $icd->id, 'art' => 'primär']);
    CareEvent::create(['resident_id' => $r->id, 'indicator' => QualityIndicator::Sturz, 'datum' => '2026-02-10']);

    $cohort = Cohort::atStichtag('2026-02-15');
    $packages = app(AssemblePackages::class)->handle($cohort);

    expect($packages)->toHaveCount(1)
        ->and($packages[0]->pseudonym)->toBe('R-'.$r->id)
        ->and($packages[0]->icd_codes)->toContain('F00.0')
        ->and($packages[0]->indikatoren['sturz'])->toBeTrue()
        ->and($packages[0]->indikatoren['dekubitus'])->toBeFalse();
});
