<?php

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\MeasureSchedule;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('plant eine Maßnahme mit Turnus', function () {
    $resident = Resident::factory()->create();
    $measure = CareMeasure::create([
        'resident_id' => $resident->id,
        'themenfeld' => SisTopicField::Mobilitaet,
        'beschreibung' => 'Mobilisation 2x täglich',
        'ziel' => 'Erhalt der Gehfähigkeit',
    ]);
    $schedule = MeasureSchedule::create([
        'care_measure_id' => $measure->id,
        'turnus_typ' => 'schicht',
        'turnus_daten' => ['schichten' => ['frueh', 'spaet']],
    ]);

    expect($measure->version)->toBe(1)
        ->and($measure->schedules)->toHaveCount(1)
        ->and($schedule->turnus_daten['schichten'])->toContain('frueh');
});
