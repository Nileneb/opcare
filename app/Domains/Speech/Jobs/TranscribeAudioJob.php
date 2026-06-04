<?php

namespace App\Domains\Speech\Jobs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Events\TranscriptionProgressed;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $jobId) {}

    public function handle(AudioTranscriber $transcriber): void
    {
        // WHY: Queue hat keine Request-Session — Tenant-Kontext explizit setzen;
        // Lookups withoutGlobalScopes, da der Scope erst nach dem set greift.
        $job = TranscriptionJob::withoutGlobalScopes()->findOrFail($this->jobId);
        app(CurrentTenant::class)->set(Tenant::findOrFail($job->tenant_id));

        $job->update(['status' => TranscriptionStatus::Transcribing]);
        event(new TranscriptionProgressed($job));

        $text = $transcriber->transcribe(Storage::disk('local')->path($job->audio_ref));

        $job->update(['rohtranskript' => $text, 'status' => TranscriptionStatus::Structuring]);
        event(new TranscriptionProgressed($job));

        StructureTranscriptJob::dispatch($job->id);
    }

    public function failed(Throwable $e): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->find($this->jobId);
        $job?->update(['status' => TranscriptionStatus::Failed, 'fehler' => $e->getMessage()]);
    }
}
