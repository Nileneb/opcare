<?php

use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Support\Facades\Broadcast;

// WHY: Tenant-Isolation — nur Nutzer desselben Mandanten dürfen den Job-Channel abonnieren.
Broadcast::channel('transcription.{jobId}', function ($user, int $jobId) {
    $job = TranscriptionJob::withoutGlobalScopes()->find($jobId);

    return $job !== null && $job->tenant_id === $user->tenant_id;
});
