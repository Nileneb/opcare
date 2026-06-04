<?php

namespace App\Domains\Speech\Actions;

use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Jobs\TranscribeAudioJob;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Http\UploadedFile;

class StartTranscription
{
    public function handle(int $residentId, string $kontext, UploadedFile $audio): TranscriptionJob
    {
        $path = $audio->store('speech/tmp', 'local');

        $job = TranscriptionJob::create([
            'resident_id' => $residentId,
            'kontext' => $kontext,
            'audio_ref' => $path,
            'status' => TranscriptionStatus::Queued,
        ]);

        TranscribeAudioJob::dispatch($job->id);

        return $job;
    }
}
