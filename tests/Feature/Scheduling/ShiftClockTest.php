<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Support\TimeslotClock;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Support\ShiftClock;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('liest die Slot-Uhrzeit aus der Schicht-Konfiguration des Mandanten', function () {
    Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['morgens' => '07:30'],
    ]);

    expect(ShiftClock::for(AdministrationTimeslot::Morgens))->toBe('07:30')
        ->and(TimeslotClock::for(AdministrationTimeslot::Morgens))->toBe('07:30');
});

it('fällt ohne Schicht-Konfiguration auf den config-Default zurück', function () {
    // kein Shift angelegt → Default aus config/medication.php
    expect(TimeslotClock::for(AdministrationTimeslot::Mittags))->toBe('12:00');
});
