<?php

use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Support\TimeslotClock;

it('kennt sechs feste Tageszeiten + Bedarf und liefert Standard-Uhrzeiten', function () {
    expect(AdministrationTimeslot::scheduled())->toHaveCount(6)
        ->and(AdministrationTimeslot::Morgens->label())->toBe('Morgens')
        ->and(TimeslotClock::for(AdministrationTimeslot::Morgens))->toBe('08:00');
});

it('liefert Einheiten je Vitalwert', function () {
    expect(VitalType::Blutdruck->einheit())->toBe('mmHg')
        ->and(VitalType::Schmerz->einheit())->toBe('NRS 0–10');
});
