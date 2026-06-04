<?php

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Actions\ApproveTranscription;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('übernimmt einen geprüften Vorschlag als SIS und schließt den Job ab', function () {
    $resident = Resident::factory()->create();
    $reviewer = User::factory()->create(['tenant_id' => $resident->tenant_id]);

    $job = TranscriptionJob::create([
        'resident_id' => $resident->id,
        'kontext' => 'mobilitaet',
        'status' => TranscriptionStatus::Review,
        'rohtranskript' => 'Geht am Rollator.',
        'sis_vorschlag' => ['felder' => [['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.']]],
    ]);

    $sis = app(ApproveTranscription::class)->handle(
        $job,
        $reviewer->id,
        ['felder' => [['themenfeld' => 'mobilitaet', 'freitext' => 'Geht sicher am Rollator.']]],
    );

    expect($sis)->toBeInstanceOf(SisAssessment::class)
        ->and($sis->topicFields->first()->freitext)->toBe('Geht sicher am Rollator.')
        ->and($job->fresh()->status)->toBe(TranscriptionStatus::Done)
        ->and($job->fresh()->reviewer_id)->toBe($reviewer->id)
        ->and($job->fresh()->freigegeben_at)->not->toBeNull();
});
