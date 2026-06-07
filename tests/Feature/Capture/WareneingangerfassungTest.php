<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Enums\PositionStatus;
use App\Domains\Capture\Models\LieferscheinAnalyse;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Capture\Wareneingangerfassung;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(config('opcare.media_disk', 'media'));
    config(['speech.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'WE-Test', 'slug' => 'we-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('kueche');

    $this->mehl = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weizenmehl Type 405',
        'einheit' => 'Sack',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->butter = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Markenbutter 250g',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

it('verwehrt Zugriff ohne passende Rolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Wareneingangerfassung::class)->assertForbidden();
});

it('erlaubt Zugriff mit Buchhaltung-Rolle', function () {
    $this->actingAs($this->user);
    Livewire::test(Wareneingangerfassung::class)->assertOk();
});

it('analysiert ein Lieferschein-Foto und legt eine Analyse mit Positionen an', function () {
    $this->actingAs($this->user);

    $file = UploadedFile::fake()->image('ls.jpg');

    Livewire::test(Wareneingangerfassung::class)
        ->set('foto', $file)
        ->call('analysieren')
        ->assertHasNoErrors();

    expect(LieferscheinAnalyse::count())->toBe(1);

    $analyse = LieferscheinAnalyse::with('positionen')->first();
    expect($analyse->positionen)->toHaveCount(2);
});

it('Analyse-View zeigt Beleg-Text der Positionen', function () {
    $this->actingAs($this->user);

    $file = UploadedFile::fake()->image('ls.jpg');

    Livewire::test(Wareneingangerfassung::class)
        ->set('foto', $file)
        ->call('analysieren')
        ->assertSee('Weizenmehl');
});

it('bestätigt eine Position und erhöht den Artikelbestand', function () {
    $this->actingAs($this->user);

    $file = UploadedFile::fake()->image('ls.jpg');

    $component = Livewire::test(Wareneingangerfassung::class)
        ->set('foto', $file)
        ->call('analysieren');

    $analyse = LieferscheinAnalyse::with('positionen')->first();
    $pid = $analyse->positionen->first()->id;

    $component
        ->set("ist.{$pid}.artikel_id", $this->mehl->id)
        ->set("ist.{$pid}.menge", 10)
        ->set("ist.{$pid}.preis", null)
        ->set("ist.{$pid}.charge", null)
        ->set("ist.{$pid}.mhd", null)
        ->set("ist.{$pid}.bestellposition_id", null)
        ->call('bestaetige', $pid)
        ->assertHasNoErrors();

    $position = $analyse->positionen->first()->fresh();
    expect($position->status)->toBe(PositionStatus::Bestaetigt);

    $this->mehl->refresh();
    expect((float) $this->mehl->bestand)->toBe(10.0);
});

it('Artikel aus Fremd-Tenant schlägt tenant-scope-Validierung fehl', function () {
    $andererTenant = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd']);
    $fremdArtikel = Artikel::create([
        'tenant_id' => $andererTenant->id,
        'name' => 'Fremd-Artikel',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->actingAs($this->user);

    $file = UploadedFile::fake()->image('ls.jpg');

    $component = Livewire::test(Wareneingangerfassung::class)
        ->set('foto', $file)
        ->call('analysieren');

    $analyse = LieferscheinAnalyse::with('positionen')->first();
    $pid = $analyse->positionen->first()->id;

    $component
        ->set("ist.{$pid}.artikel_id", $fremdArtikel->id)
        ->set("ist.{$pid}.menge", 5)
        ->call('bestaetige', $pid)
        ->assertHasErrors(["ist.{$pid}.artikel_id"]);
});

it('verwirft eine Position ohne Wareneingang zu buchen', function () {
    $this->actingAs($this->user);

    $file = UploadedFile::fake()->image('ls.jpg');

    $component = Livewire::test(Wareneingangerfassung::class)
        ->set('foto', $file)
        ->call('analysieren');

    $analyse = LieferscheinAnalyse::with('positionen')->first();
    $pid = $analyse->positionen->first()->id;

    $component->call('verwerfe', $pid);

    $position = $analyse->positionen->first()->fresh();
    expect($position->status)->toBe(PositionStatus::Verworfen);
    expect((float) $this->mehl->fresh()->bestand)->toBe(0.0);
});
