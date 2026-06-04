<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Support\TimeslotClock;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Support\ShiftClock;
use Illuminate\Support\Facades\DB;

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

it('cacht die Slot-Auflösung je Mandant (kein N+1 über wiederholte Aufrufe)', function () {
    Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['morgens' => '07:30', 'mittags' => '12:30'],
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    ShiftClock::for(AdministrationTimeslot::Morgens);
    ShiftClock::for(AdministrationTimeslot::Mittags);
    ShiftClock::for(AdministrationTimeslot::Morgens);
    $shiftQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "shifts"'))->count();
    DB::disableQueryLog();

    expect($shiftQueries)->toBe(1);
});

it('verwirft den Cache beim Wechsel des Mandanten-Kontexts', function () {
    Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['morgens' => '07:30'],
    ]);
    expect(ShiftClock::for(AdministrationTimeslot::Morgens))->toBe('07:30');

    $anderer = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($anderer);

    expect(ShiftClock::for(AdministrationTimeslot::Morgens))->toBeNull();
});
