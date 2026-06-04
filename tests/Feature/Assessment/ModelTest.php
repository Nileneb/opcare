<?php

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('castet Instrument-Felder und ist versionierbar/tenant-scoped', function () {
    $instr = Instrument::create([
        'name' => 'Braden', 'risk_type' => RiskType::Dekubitus, 'direction' => ScaleDirection::LowerIsWorse,
        'risk_bands' => [['band' => 'hoch', 'min' => null, 'max' => 12]],
    ]);

    expect($instr->risk_type)->toBe(RiskType::Dekubitus)
        ->and($instr->direction)->toBe(ScaleDirection::LowerIsWorse)
        ->and($instr->version)->toBe(1)
        ->and(Instrument::current()->count())->toBe(1);

    $v2 = $instr->reviseWith(['name' => 'Braden (rev.)']);
    expect($v2->version)->toBe(2)
        ->and(Instrument::current()->count())->toBe(1)
        ->and($instr->fresh()->isSuperseded())->toBeTrue();
});
