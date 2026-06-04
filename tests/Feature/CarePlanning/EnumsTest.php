<?php

use App\Domains\CarePlanning\Enums\SisTopicField;

it('hat sechs SIS-Themenfelder', function () {
    expect(SisTopicField::cases())->toHaveCount(6)
        ->and(SisTopicField::Kognition->label())->toBe('Kognition & Kommunikation');
});
