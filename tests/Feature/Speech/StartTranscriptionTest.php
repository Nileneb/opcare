<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Actions\StartTranscription;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Jobs\TranscribeAudioJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('speichert Audio temporär, legt Job an und dispatcht die Transkription', function () {
    $resident = Resident::factory()->create();
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');

    $job = app(StartTranscription::class)->handle($resident->id, 'mobilitaet', $audio);

    expect($job->status)->toBe(TranscriptionStatus::Queued)
        ->and($job->audio_ref)->not->toBeNull();
    Storage::disk('local')->assertExists($job->audio_ref);
    Queue::assertPushed(TranscribeAudioJob::class, fn ($j) => $j->jobId === $job->id);
});
