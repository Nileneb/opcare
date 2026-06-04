<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Actions\StartTranscription;
use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Testing\FakeAudioTranscriber;
use App\Domains\Speech\Testing\FakeSisStructurer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    app()->instance(AudioTranscriber::class, new FakeAudioTranscriber('Frau M. geht am Rollator.'));
    app()->instance(SisStructurer::class, new FakeSisStructurer);
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('durchläuft die Kette bis status review und löscht das Audio', function () {
    $resident = Resident::factory()->create();
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');

    $job = app(StartTranscription::class)->handle($resident->id, 'mobilitaet', $audio);
    $job->refresh();

    expect($job->status)->toBe(TranscriptionStatus::Review)
        ->and($job->rohtranskript)->toBe('Frau M. geht am Rollator.')
        ->and($job->sis_vorschlag['felder'][0]['themenfeld'])->toBe('mobilitaet')
        ->and($job->audio_ref)->toBeNull();
});
