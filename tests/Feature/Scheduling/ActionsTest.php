<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Actions\CancelCalendarEvent;
use App\Domains\Scheduling\Actions\CreateCalendarEvent;
use App\Domains\Scheduling\Actions\CreateShift;
use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Data\RecurrenceData;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Data\ShiftData;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\ShiftAssignment;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('legt eine Schicht an', function () {
    $shift = (new CreateShift)->handle(new ShiftData(
        name: 'Spät', kind: ShiftKind::Spaet, beginn: '14:00', ende: '22:00',
        timeslots: ['nachmittags' => '15:00', 'abends' => '18:00'],
    ));

    expect($shift->name)->toBe('Spät')->and($shift->timeslots)->toHaveKey('abends');
});

it('weist eine Schicht idempotent zu (kein Doppeleintrag)', function () {
    $shift = (new CreateShift)->handle(new ShiftData(name: 'Früh', kind: ShiftKind::Frueh, beginn: '06:00', ende: '14:00'));

    $data = new ShiftAssignmentData(user_id: $this->user->id, shift_id: $shift->id, dienst_am: '2026-06-15');
    (new AssignShift)->handle($data);
    (new AssignShift)->handle($data);

    expect(ShiftAssignment::count())->toBe(1);
});

it('legt einen wiederkehrenden Kalendertermin samt RecurrenceRule an und sagt ihn ab', function () {
    $event = (new CreateCalendarEvent)->handle(new CalendarEventData(
        type: CalendarEventType::Therapie, titel: 'Physio', beginnt_am: '2026-06-15 11:00:00',
        endet_am: '2026-06-15 11:30:00', created_by: $this->user->id,
        recurrence: new RecurrenceData(freq: RecurrenceFreq::Weekly, byday: [1], intervall: 1),
    ));

    expect($event->istWiederkehrend())->toBeTrue()
        ->and($event->recurrenceRule->freq)->toBe(RecurrenceFreq::Weekly);

    (new CancelCalendarEvent)->handle($event);
    expect($event->fresh()->istAbgesagt())->toBeTrue();
});
