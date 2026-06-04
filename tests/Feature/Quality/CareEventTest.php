<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;

beforeEach(fn () => app(CurrentTenant::class)->set(Tenant::create(['name' => 'A', 'slug' => 'a'])));

it('erfasst ein Sturz-Ereignis mit Schweregrad', function () {
    $resident = Resident::factory()->create();
    $e = CareEvent::create([
        'resident_id' => $resident->id,
        'indicator' => QualityIndicator::Sturz,
        'datum' => '2026-02-15',
        'severity' => EventSeverity::MitFolgen,
        'details' => ['ort' => 'Bad', 'verletzung' => 'Platzwunde'],
    ]);

    expect($e->indicator)->toBe(QualityIndicator::Sturz)
        ->and($e->severity)->toBe(EventSeverity::MitFolgen)
        ->and($e->details['ort'])->toBe('Bad');
});
