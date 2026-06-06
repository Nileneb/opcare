<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\MediaShare;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Masterdata\ResidentMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('leserecht');
    $this->pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->pfk->assignRole('pflegefachkraft');
    $this->resident = Resident::factory()->create();
});

it('lädt ein medizinisches Dokument hoch und setzt die Aufbewahrungsfrist (§ 630f BGB)', function () {
    $this->actingAs($this->pfk);

    Livewire::test(ResidentMedia::class, ['resident' => $this->resident])
        ->set('datei', UploadedFile::fake()->create('arztbrief.pdf', 12))
        ->set('kategorie', 'befund')
        ->call('speichern')->assertHasNoErrors();

    $media = $this->resident->fresh()->getMedia('documents');
    expect($media)->toHaveCount(1)
        ->and($media->first()->getCustomProperty('retention_until'))->toBe(today()->addYears(10)->toDateString());
});

it('verlangt für ein Profilfoto eine Einwilligung (§ 22 KUG)', function () {
    $this->actingAs($this->pfk);

    Livewire::test(ResidentMedia::class, ['resident' => $this->resident])
        ->set('datei', UploadedFile::fake()->image('foto.jpg'))
        ->set('kategorie', 'profilfoto')
        ->set('einwilligung', '')
        ->call('speichern')->assertHasErrors('einwilligung');

    Livewire::test(ResidentMedia::class, ['resident' => $this->resident])
        ->set('datei', UploadedFile::fake()->image('foto.jpg'))
        ->set('kategorie', 'profilfoto')
        ->set('einwilligung', 'gesetzlicher Betreuer Herr Meier')
        ->call('speichern')->assertHasNoErrors();

    expect($this->resident->fresh()->getMedia('documents')->first()->getCustomProperty('einwilligung_von'))
        ->toBe('gesetzlicher Betreuer Herr Meier');
});

it('erzeugt eine protokollierte, ablaufende Freigabe und lässt den Download zu', function () {
    $this->actingAs($this->pfk);
    $media = $this->resident->addMedia(UploadedFile::fake()->create('befund.pdf', 8))->toMediaCollection('documents');

    $comp = Livewire::test(ResidentMedia::class, ['resident' => $this->resident])
        ->call('teilenStart', $media->id)
        ->set('teilen_typ', 'physician')->set('teilen_empfaenger', 'Dr. Schmidt')->set('teilen_minuten', 60)
        ->call('teilenSpeichern')->assertHasNoErrors();

    $share = MediaShare::where('media_id', $media->id)->firstOrFail();
    expect($share->share_type)->toBe('physician')->and($share->recipient_name)->toBe('Dr. Schmidt');

    $link = $comp->get('shareLink');
    expect($link)->toContain('/dokumente/'.$media->id);

    $this->get($link)->assertOk();
    expect($share->fresh()->accessed_at)->not->toBeNull();
});

it('verwehrt Upload ohne Schreibrecht (leserecht)', function () {
    $leser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leser->assignRole('leserecht');
    $this->actingAs($leser);

    Livewire::test(ResidentMedia::class, ['resident' => $this->resident])
        ->set('datei', UploadedFile::fake()->create('x.pdf', 5))
        ->set('kategorie', 'befund')
        ->call('speichern')->assertForbidden();
});
