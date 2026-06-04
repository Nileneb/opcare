<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Models\Shift;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('castet Shift-Felder und ist tenant-scoped', function () {
    $shift = Shift::create([
        'name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00',
        'timeslots' => ['nacht_mo' => '06:00', 'morgens' => '08:00', 'mittags' => '12:00'],
    ]);

    expect($shift->kind)->toBe(ShiftKind::Frueh)
        ->and($shift->timeslots)->toHaveKey('morgens')
        ->and($shift->tenant_id)->toBe($this->tenant->id);
});

it('castet CalendarEvent-Datumsfelder', function () {
    $e = CalendarEvent::create([
        'type' => CalendarEventType::Arzttermin, 'titel' => 'HNO',
        'beginnt_am' => '2026-06-20 10:00:00', 'created_by' => 1,
    ]);

    expect($e->beginnt_am)->toBeInstanceOf(Carbon::class)
        ->and($e->type)->toBe(CalendarEventType::Arzttermin)
        ->and($e->istAbgesagt())->toBeFalse();
});
