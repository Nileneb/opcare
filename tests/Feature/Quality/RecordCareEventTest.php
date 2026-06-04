<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Actions\RecordCareEvent;
use App\Domains\Quality\Data\CareEventData;
use App\Domains\Quality\Enums\QualityIndicator;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erfasst ein Ereignis über die Action', function () {
    $resident = Resident::factory()->create();
    $e = app(RecordCareEvent::class)->handle(new CareEventData(
        resident_id: $resident->id, indicator: QualityIndicator::Dekubitus->value, datum: '2026-03-01', severity: 'mittel',
    ));
    expect($e->indicator)->toBe(QualityIndicator::Dekubitus);
});
