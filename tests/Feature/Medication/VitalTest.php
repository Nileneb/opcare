<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\RecordVital;
use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->nurse = User::factory()->create(['tenant_id' => $t->id]);
});

it('erfasst einen Vitalwert mit Einheit', function () {
    $resident = Resident::factory()->create();
    $v = app(RecordVital::class)->handle(new VitalData(
        resident_id: $resident->id, typ: VitalType::Schmerz->value, wert: 6, gemessen_von: $this->nurse->id,
    ));
    expect($v->typ)->toBe(VitalType::Schmerz)->and($v->einheit)->toBe('NRS 0–10')->and((float) $v->wert)->toBe(6.0);
});
