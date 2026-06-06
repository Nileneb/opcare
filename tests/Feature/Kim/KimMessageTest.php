<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Kim\Contracts\KimTransport;
use App\Domains\Kim\Data\KimMessage;
use App\Domains\Kim\DormantKimTransport;
use App\Domains\Kim\KimMessageComposer;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Facades\Log;

function kimMessage(): KimMessage
{
    return new KimMessage(
        from: 'einrichtung@opcare.kim.telematik-test',
        to: 'folge@kim.telematik-test',
        subject: 'Pflegeüberleitung — Test',
        dienstkennung: 'Dokument;1.0',
        body: 'Anbei der Überleitungsbogen.',
        attachmentContent: '{"resourceType":"Bundle","id":"x"}',
        attachmentFilename: 'ueberleitungsbogen-1.fhir.json',
    );
}

it('komponiert eine KIM-konforme MIME-Nachricht mit Dienstkennung + FHIR-Anhang', function () {
    $eml = (new KimMessageComposer)->compose(kimMessage());

    expect($eml)
        ->toContain('X-KIM-Dienstkennung: Dokument;1.0')
        ->toContain('To: folge@kim.telematik-test')
        ->toContain('From: einrichtung@opcare.kim.telematik-test')
        ->toContain('Subject:')
        // Anhang: FHIR-Dokument mit korrektem Content-Type + Dateiname
        ->toContain('application/fhir+json')
        ->toContain('ueberleitungsbogen-1.fhir.json');
});

it('komponiert im dormant-Transport, sendet aber NICHT (sichtbar protokolliert)', function () {
    Log::spy();

    $eml = app(DormantKimTransport::class)->send(kimMessage());

    expect($eml)->toContain('X-KIM-Dienstkennung');
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'stillgelegt'))->once();
});

it('bindet KimTransport per Default auf den dormant-Transport', function () {
    expect(app(KimTransport::class))->toBeInstanceOf(DormantKimTransport::class);
});

it('komponiert den Überleitungsbogen eines Bewohners als KIM-Nachricht (Command)', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $resident = Resident::factory()->create(['name' => 'Erika Muster']);

    $this->artisan('kim:ueberleitung', ['resident' => $resident->id, '--to' => 'folge@kim.telematik-test'])
        ->assertSuccessful();
});
