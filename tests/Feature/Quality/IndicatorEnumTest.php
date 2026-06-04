<?php

use App\Domains\Quality\Enums\QualityIndicator;

it('kennt die QS-Indikatoren mit Labels', function () {
    expect(QualityIndicator::Sturz->label())->toBe('Sturz')
        ->and(QualityIndicator::Dekubitus->label())->toBe('Dekubitus (neu erworben)')
        ->and(count(QualityIndicator::cases()))->toBeGreaterThanOrEqual(6);
});
