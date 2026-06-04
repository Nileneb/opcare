<?php

namespace App\Domains\Speech\Jobs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Contracts\SisStructurer;
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

class StructureTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $jobId) {}

    public function handle(SisStructurer $structurer): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->findOrFail($this->jobId);
        app(CurrentTenant::class)->set(Tenant::findOrFail($job->tenant_id));

        $vorschlag = $structurer->structure($job->rohtranskript, $job->kontext);

        // WHY: Audio löschen (Datensparsamkeit, Art. 5 DSGVO) — Rohtext + Vorschlag reichen ab hier.
        if ($job->audio_ref) {
            Storage::disk('local')->delete($job->audio_ref);
        }

        $job->update([
            'sis_vorschlag' => $vorschlag->toArray(),
            'audio_ref' => null,
            'status' => TranscriptionStatus::Review,
        ]);
        event(new TranscriptionProgressed($job));
    }

    public function failed(Throwable $e): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->find($this->jobId);
        $job?->update(['status' => TranscriptionStatus::Failed, 'fehler' => $e->getMessage()]);
    }
}
