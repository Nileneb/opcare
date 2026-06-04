<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Events\TranscriptionProgressed;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('feuert ein Fortschritts-Event mit Job-Status', function () {
    Event::fake([TranscriptionProgressed::class]);
    $resident = Resident::factory()->create();
    $job = TranscriptionJob::create([
        'resident_id' => $resident->id, 'kontext' => 'mobilitaet',
        'audio_ref' => 'x', 'status' => 'queued',
    ]);

    event(new TranscriptionProgressed($job));

    Event::assertDispatched(TranscriptionProgressed::class, fn ($e) => $e->job->is($job));
});
