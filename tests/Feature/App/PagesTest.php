<?php

use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use App\Livewire\Residents;
use App\Livewire\ResidentShow;
use App\Livewire\Speech;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    config(['speech.fake' => true, 'queue.default' => 'sync']);
    $this->seed(RolesSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->admin = User::query()->where('email', 'admin@opcare.local')->first();
    $this->actingAs($this->admin);
    app(CurrentTenant::class)->set($this->admin->tenant);
});

it('liefert alle App-Seiten für eingeloggte Nutzer aus', function () {
    $resident = Resident::query()->first();
    foreach (['/', '/bewohner', '/bewohner/'.$resident->id, '/spracherfassung', '/einrichtung', '/profil'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('legt einen Bewohner über die UI an', function () {
    Livewire::test(Residents::class)
        ->set('name', 'Test Bewohner')
        ->set('geburtsdatum', '1942-04-04')
        ->set('geschlecht', 'm')
        ->set('aufnahme_am', '2026-06-01')
        ->set('pflegegrad', 2)
        ->call('save')
        ->assertHasNoErrors();

    expect(Resident::where('name', 'Test Bewohner')->exists())->toBeTrue();
});

it('legt eine SIS-Erhebung über die Detailseite an', function () {
    $resident = Resident::factory()->create();

    Livewire::test(ResidentShow::class, ['resident' => $resident])
        ->set('sis_eingangsfrage', 'Möchte mobil bleiben.')
        ->set('sis_felder.mobilitaet', 'Geht am Rollator.')
        ->set('sis_risiken', ['sturz'])
        ->call('createSis')
        ->assertHasNoErrors();

    $sis = SisAssessment::where('resident_id', $resident->id)->first();
    expect($sis)->not->toBeNull()
        ->and($sis->topicFields)->toHaveCount(1)
        ->and($sis->riskItems)->toHaveCount(1);
});

it('durchläuft den Sprach-Workflow bis zur Freigabe (Fakes)', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create();

    $component = Livewire::test(Speech::class)
        ->set('resident_id', $resident->id)
        ->set('kontext', 'mobilitaet')
        ->call('startDemo')
        ->assertHasNoErrors();

    $job = TranscriptionJob::where('resident_id', $resident->id)->first();
    expect($job->status)->toBe(TranscriptionStatus::Review)
        ->and($job->audio_ref)->toBeNull();

    $component->call('approve', $job->id)->assertHasNoErrors();

    expect($job->fresh()->status)->toBe(TranscriptionStatus::Done)
        ->and(SisAssessment::where('resident_id', $resident->id)->exists())->toBeTrue();
});
