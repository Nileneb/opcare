<?php

use App\Domains\Communication\Models\Konversation;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Support\Facades\Broadcast;

// WHY: Tenant-Isolation — nur Nutzer desselben Mandanten dürfen den Job-Channel abonnieren.
Broadcast::channel('transcription.{jobId}', function ($user, int $jobId) {
    $job = TranscriptionJob::withoutGlobalScopes()->find($jobId);

    return $job !== null && $job->tenant_id === $user->tenant_id;
});

// WHY: Tenant-Isolation + Mitgliedschaft — nur Mitglieder der Konversation desselben Tenants
// dürfen den Chat-Channel abonnieren (IDOR-Schutz via tenant_id + istMitglied).
Broadcast::channel('konversation.{id}', function ($user, int $id) {
    $konversation = Konversation::withoutGlobalScopes()
        ->where('id', $id)
        ->where('tenant_id', $user->tenant_id)
        ->first();

    return $konversation !== null && $konversation->istMitglied($user->id) === true;
});
