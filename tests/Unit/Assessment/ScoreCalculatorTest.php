<?php

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\Assessment\Support\RiskBandResolver;
use App\Domains\Assessment\Support\ScoreCalculator;

it('summiert die Punkte der gewählten Optionen', function () {
    expect((new ScoreCalculator)->sum([3, 2, 4, 1]))->toBe(10);
});

it('bildet den Braden-Score (lower_is_worse) auf die Risikostufe ab', function () {
    $bands = [
        ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
        ['band' => 'hoch', 'min' => 10, 'max' => 12],
        ['band' => 'mittel', 'min' => 13, 'max' => 14],
        ['band' => 'gering', 'min' => 15, 'max' => 18],
        ['band' => 'kein', 'min' => 19, 'max' => null],
    ];
    $resolver = new RiskBandResolver;

    expect($resolver->resolve(11, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::Hoch)
        ->and($resolver->resolve(8, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::SehrHoch)
        ->and($resolver->resolve(20, $bands, ScaleDirection::LowerIsWorse))->toBe(RiskBand::Kein);
});

it('bildet eine higher_is_worse-Skala korrekt ab', function () {
    $bands = [
        ['band' => 'gering', 'min' => null, 'max' => 3],
        ['band' => 'mittel', 'min' => 4, 'max' => 6],
        ['band' => 'hoch', 'min' => 7, 'max' => null],
    ];

    expect((new RiskBandResolver)->resolve(8, $bands, ScaleDirection::HigherIsWorse))->toBe(RiskBand::Hoch);
});
