<?php

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\CarePlanning\Models\SisTopicFieldEntry;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('speichert ein Themenfeld mit Freitext und Strukturdaten', function () {
    $sis = SisAssessment::factory()->create();
    $entry = SisTopicFieldEntry::create([
        'sis_assessment_id' => $sis->id,
        'themenfeld' => SisTopicField::Mobilitaet,
        'freitext' => 'Geht am Rollator.',
        'strukturdaten' => ['rollator' => true, 'sturzrisiko' => 'mittel'],
    ]);

    expect($entry->themenfeld)->toBe(SisTopicField::Mobilitaet)
        ->and($entry->strukturdaten['rollator'])->toBeTrue()
        ->and($sis->topicFields)->toHaveCount(1);
});
