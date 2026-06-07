<?php

use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Vision\Models\ProductLabel;
use App\Domains\Vision\Models\RegalAufnahme;
use App\Domains\Vision\Models\RegalDetection;
use App\Livewire\Vision\Regalzaehlung;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(config('opcare.media_disk', 'media'));
    config(['vision.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'RegalTest-Tenant', 'slug' => 'regal-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('pflegefachkraft');
    Role::findOrCreate('haustechnik');

    $this->artikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Einweghandschuh Box',
        'einheit' => 'Stk',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 10,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $this->label = ProductLabel::create([
        'tenant_id' => $this->tenant->id,
        'yolo_label' => 'box',
        'artikel_id' => $this->artikel->id,
        'multiplier' => 1,
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

// ─── Gate ─────────────────────────────────────────────────────────────────────

it('verweigert Zugriff ohne passende Rolle (403)', function () {
    $haustechnik = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $haustechnik->assignRole('haustechnik');
    $this->actingAs($haustechnik);

    Livewire::test(Regalzaehlung::class)->assertForbidden();
});

it('erlaubt Zugriff mit Buchhaltung-Rolle', function () {
    $this->actingAs($this->user);

    Livewire::test(Regalzaehlung::class)->assertOk();
});

it('erlaubt Zugriff mit Pflegefachkraft-Rolle', function () {
    $fachkraft = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $fachkraft->assignRole('pflegefachkraft');
    $this->actingAs($fachkraft);

    Livewire::test(Regalzaehlung::class)->assertOk();
});

// ─── Zählen ───────────────────────────────────────────────────────────────────

it('zaehlen legt RegalAufnahme und RegalDetection mit Mengenvorschlag an', function () {
    $this->actingAs($this->user);

    // WHY(GC-FALLE): UploadedFile in Variable halten bis nach Assertion.
    $fotoFile = UploadedFile::fake()->image('regal.jpg');

    Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen')
        ->assertHasNoErrors();

    // FakeVisionClient: counts['box'] = 3, multiplier = 1 → menge_vorschlag = 3
    expect(RegalAufnahme::where('tenant_id', $this->tenant->id)->count())->toBe(1);

    $det = RegalDetection::where('tenant_id', $this->tenant->id)->first();
    expect($det)->not->toBeNull()
        ->and($det->label)->toBe('box')
        ->and((float) $det->menge_vorschlag)->toBe(3.0)
        ->and($det->artikel_id)->toBe($this->artikel->id);

    unset($fotoFile);
});

it('zaehlen zeigt Hinweis wenn kein trainiertes Modell vorhanden', function () {
    $this->actingAs($this->user);

    $fotoFile = UploadedFile::fake()->image('regal2.jpg');

    Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen')
        ->assertSet('keinModellHinweis', 'Kein trainiertes Modell vorhanden — Basis-Erkennung wird verwendet.');

    unset($fotoFile);
});

// ─── Buchen ───────────────────────────────────────────────────────────────────

it('buchen setzt ist_menge der Inventurposition korrekt', function () {
    $this->actingAs($this->user);

    $inventur = app(InventurStarten::class)->handle(now()->toDateString(), null, $this->user->id);

    $fotoFile = UploadedFile::fake()->image('regal3.jpg');

    $component = Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen')
        ->assertHasNoErrors();

    $det = RegalDetection::where('tenant_id', $this->tenant->id)->first();
    expect($det)->not->toBeNull();

    $component
        ->set('inventurId', $inventur->id)
        ->set("ist.{$det->id}", 5.0)
        ->call('buchen', $det->id)
        ->assertHasNoErrors();

    $position = Inventurposition::where('inventur_id', $inventur->id)
        ->where('artikel_id', $this->artikel->id)
        ->first();

    expect($position)->not->toBeNull()
        ->and((float) $position->ist_menge)->toBe(5.0)
        ->and($position->gezaehlt_von)->toBe($this->user->id);

    unset($fotoFile);
});

it('buchen in geschlossene Inventur schlägt fehl', function () {
    $this->actingAs($this->user);

    $inventur = app(InventurStarten::class)->handle(now()->toDateString(), null, $this->user->id);
    $inventur->update(['status' => 'abgeschlossen']);

    $fotoFile = UploadedFile::fake()->image('regal4.jpg');

    $component = Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen');

    $det = RegalDetection::where('tenant_id', $this->tenant->id)->first();

    $component
        ->set('inventurId', $inventur->id)
        ->set("ist.{$det->id}", 2.0)
        ->call('buchen', $det->id)
        ->assertHasErrors('inventurId');

    unset($fotoFile);
});

it('buchen ohne passende Inventurposition erzeugt Fehler (kein stilles Anlegen)', function () {
    $this->actingAs($this->user);

    $andererArtikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Produkt ohne Position',
        'einheit' => 'Stk',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    // Inventur nur für andererArtikel — aber detection zeigt auf $this->artikel
    $inventur = Inventur::create([
        'tenant_id' => $this->tenant->id,
        'stichtag' => now()->toDateString(),
        'status' => 'offen',
        'erstellt_von' => $this->user->id,
    ]);
    // keine Position für $this->artikel

    $fotoFile = UploadedFile::fake()->image('regal5.jpg');

    $component = Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen');

    $det = RegalDetection::where('tenant_id', $this->tenant->id)->first();

    $component
        ->set('inventurId', $inventur->id)
        ->set("ist.{$det->id}", 1.0)
        ->call('buchen', $det->id)
        ->assertHasErrors("ist.{$det->id}");

    expect(Inventurposition::where('inventur_id', $inventur->id)->count())->toBe(0);

    unset($fotoFile);
});

// ─── Tenant-Scope (IDOR) ──────────────────────────────────────────────────────

it('buchen mit Fremd-Tenant-Inventur schlägt Validierung fehl', function () {
    $this->actingAs($this->user);

    $fremdTenant = Tenant::create(['name' => 'Fremd-Regal', 'slug' => 'fremd-regal']);
    $fremdInventur = Inventur::create([
        'tenant_id' => $fremdTenant->id,
        'stichtag' => now()->toDateString(),
        'status' => 'offen',
        'erstellt_von' => null,
    ]);

    $fotoFile = UploadedFile::fake()->image('regal6.jpg');

    $component = Livewire::test(Regalzaehlung::class)
        ->set('foto', $fotoFile)
        ->call('zaehlen');

    $det = RegalDetection::where('tenant_id', $this->tenant->id)->first();

    $component
        ->set('inventurId', $fremdInventur->id)
        ->set("ist.{$det->id}", 1.0)
        ->call('buchen', $det->id)
        ->assertHasErrors('inventurId');

    unset($fotoFile);
});
