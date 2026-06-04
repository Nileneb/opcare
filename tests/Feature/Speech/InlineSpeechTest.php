<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // SPEECH_FAKE=true (phpunit.xml) → Fake-Adapter sind gebunden.
    $this->seed(RolesSeeder::class);
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('transkribiert hochgeladenes Audio synchron zu Text', function () {
    Storage::fake('local');

    $this->actingAs($this->user)
        ->postJson(route('speech.transcribe'), [
            'audio' => UploadedFile::fake()->create('note.webm', 12, 'audio/webm'),
        ])
        ->assertOk()
        ->assertJsonPath('text', 'Frau M. geht heute sicher am Rollator.');

    // Audio wurde nach ASR wieder gelöscht (kein tmp-Rest).
    expect(Storage::disk('local')->files('speech/tmp'))->toBeEmpty();
});

it('lehnt Transkription ohne Audio ab', function () {
    $this->actingAs($this->user)
        ->postJson(route('speech.transcribe'), [])
        ->assertStatus(422);
});

it('optimiert Text per LLM (Fake: säubert + formt Satz)', function () {
    $this->actingAs($this->user)
        ->postJson(route('speech.optimize'), ['text' => '  geht  am rollator  '])
        ->assertOk()
        ->assertJson(['text' => 'Geht am rollator.']);
});

it('schützt die Sprach-Endpoints vor Gästen', function () {
    $this->postJson(route('speech.transcribe'), [])->assertUnauthorized();
    $this->postJson(route('speech.optimize'), ['text' => 'x'])->assertUnauthorized();
});
