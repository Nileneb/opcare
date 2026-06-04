<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen Transkriptions-Job im Status queued an', function () {
    $resident = Resident::factory()->create();
    $job = TranscriptionJob::create([
        'resident_id' => $resident->id,
        'kontext' => 'mobilitaet',
        'audio_ref' => 'speech/tmp/a.webm',
        'status' => TranscriptionStatus::Queued,
    ]);

    expect($job->status)->toBe(TranscriptionStatus::Queued)
        ->and($job->resident->is($resident))->toBeTrue();
});
