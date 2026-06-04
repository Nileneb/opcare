<?php

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Support\RecurrenceExpander;
use Illuminate\Support\Carbon;

it('expandiert tägliche Wiederholung im Zeitraum', function () {
    $start = Carbon::parse('2026-06-01 09:00');
    $rule = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => null, 'count' => null];

    $occ = (new RecurrenceExpander)->expand($start, $rule, '2026-06-01', '2026-06-05');

    expect($occ)->toHaveCount(5)
        ->and($occ[0]->format('Y-m-d H:i'))->toBe('2026-06-01 09:00')
        ->and($occ[4]->format('Y-m-d H:i'))->toBe('2026-06-05 09:00');
});

it('expandiert wöchentlich nach ISO-Wochentagen (Mo+Mi)', function () {
    $start = Carbon::parse('2026-06-01 08:00'); // Montag
    $rule = ['freq' => RecurrenceFreq::Weekly, 'intervall' => 1, 'byday' => [1, 3], 'until' => null, 'count' => null];

    $occ = (new RecurrenceExpander)->expand($start, $rule, '2026-06-01', '2026-06-07');

    expect($occ)->toHaveCount(2)
        ->and($occ[0]->dayOfWeekIso)->toBe(1)
        ->and($occ[1]->dayOfWeekIso)->toBe(3);
});

it('respektiert until und count', function () {
    $start = Carbon::parse('2026-06-01 09:00');
    $ruleUntil = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => '2026-06-03', 'count' => null];
    $ruleCount = ['freq' => RecurrenceFreq::Daily, 'intervall' => 1, 'byday' => null, 'until' => null, 'count' => 2];

    expect((new RecurrenceExpander)->expand($start, $ruleUntil, '2026-06-01', '2026-06-30'))->toHaveCount(3)
        ->and((new RecurrenceExpander)->expand($start, $ruleCount, '2026-06-01', '2026-06-30'))->toHaveCount(2);
});
