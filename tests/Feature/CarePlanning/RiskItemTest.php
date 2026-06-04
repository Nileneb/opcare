<?php

use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Models\RiskItem;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('erfasst ein eingeschätztes Risiko', function () {
    $sis = SisAssessment::factory()->create();
    $risk = RiskItem::create([
        'sis_assessment_id' => $sis->id,
        'risiko' => RiskType::Sturz,
        'eingeschaetzt' => true,
        'begruendung' => 'Gangunsicherheit, Rollator.',
    ]);

    expect($risk->risiko)->toBe(RiskType::Sturz)
        ->and($risk->eingeschaetzt)->toBeTrue()
        ->and($sis->riskItems)->toHaveCount(1);
});
