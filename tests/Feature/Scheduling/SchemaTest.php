<?php

use Illuminate\Support\Facades\Schema;

it('legt die Scheduling-Tabellen mit den erwarteten Spalten an', function () {
    expect(Schema::hasColumns('shifts', ['tenant_id', 'name', 'kind', 'beginn', 'ende', 'timeslots']))->toBeTrue()
        ->and(Schema::hasColumns('shift_assignments', ['tenant_id', 'user_id', 'shift_id', 'dienst_am']))->toBeTrue()
        ->and(Schema::hasColumns('recurrence_rules', ['tenant_id', 'freq', 'intervall', 'byday', 'until']))->toBeTrue()
        ->and(Schema::hasColumns('calendar_events', ['tenant_id', 'resident_id', 'type', 'titel', 'beginnt_am', 'endet_am', 'recurrence_rule_id', 'abgesagt_am']))->toBeTrue();
});
