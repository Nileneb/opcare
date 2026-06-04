<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentDiagnosis;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('verknüpft Bewohner mit ICD-Diagnose', function () {
    $icd = IcdCode::create(['code' => 'F00.0', 'bezeichnung' => 'Demenz bei Alzheimer-Krankheit']);
    $resident = Resident::factory()->create();

    $diag = ResidentDiagnosis::create([
        'resident_id' => $resident->id,
        'icd_code_id' => $icd->id,
        'art' => 'primär',
    ]);

    expect($resident->diagnoses)->toHaveCount(1)
        ->and($diag->icdCode->code)->toBe('F00.0');
});
