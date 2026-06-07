<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Import\Models\ImportZeile;
use App\Livewire\Import\Datenimport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(config('opcare.media_disk', 'media'));
    config(['speech.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'Import-UI-Test', 'slug' => 'import-ui-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('kueche');

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

it('verwehrt Zugriff ohne passende Rolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Datenimport::class)->assertForbidden();
});

it('erlaubt Zugriff mit Buchhaltung-Rolle', function () {
    $this->actingAs($this->user);
    Livewire::test(Datenimport::class)->assertOk();
});

it('parst eine CSV und legt ImportBatch + ImportZeile an', function () {
    $this->actingAs($this->user);

    // WHY(GC-FALLE): $file muss bis nach der Assertion leben — kein inline-Ausdruck.
    $file = UploadedFile::fake()->createWithContent(
        'artikel.csv',
        "Bezeichnung;Einheit;Anfangsbestand;EK\nMehl;kg;50;2,00\n",
    );

    Livewire::test(Datenimport::class)
        ->set('datei', $file)
        ->call('parsen')
        ->assertHasNoErrors()
        ->assertSee('artikel.csv');

    expect(ImportBatch::count())->toBe(1);
    expect(ImportZeile::count())->toBe(1);
    expect(ImportZeile::first()->name)->toBe('Mehl');

    unset($file);
});

it('bestaetigeZeile importiert den Artikel und bucht FIFO-Schicht + EBK-Konto', function () {
    $this->actingAs($this->user);

    $file = UploadedFile::fake()->createWithContent(
        'artikel.csv',
        "Bezeichnung;Einheit;Anfangsbestand;EK\nMehl;kg;50;2,00\n",
    );

    $component = Livewire::test(Datenimport::class)
        ->set('datei', $file)
        ->call('parsen')
        ->assertHasNoErrors();

    $zeile = ImportZeile::first();

    $component
        ->set("ist.{$zeile->id}.aktion", 'anlegen')
        ->set("ist.{$zeile->id}.bestand", 50)
        ->set("ist.{$zeile->id}.einstandspreis", 2.00)
        ->set("ist.{$zeile->id}.matched_artikel_id", '')
        ->set("ist.{$zeile->id}.matched_lieferant_id", '')
        ->call('bestaetigeZeile', $zeile->id)
        ->assertHasNoErrors();

    $zeile->refresh();

    $artikel = Artikel::where('tenant_id', $this->tenant->id)->where('name', 'Mehl')->first();
    expect($artikel)->not->toBeNull();
    expect((float) $artikel->bestand)->toBe(50.0);

    $schicht = Lagerschicht::where('artikel_id', $artikel->id)->first();
    expect($schicht)->not->toBeNull();

    expect(AccountingDefaults::konto(AccountingDefaults::ANFANGSBESTAND)->saldo())->toBe(100.0);

    unset($file);
});

it('bestaetigeZeile lehnt matched_artikel_id aus Fremd-Tenant ab', function () {
    $this->actingAs($this->user);

    $fremdTenant = Tenant::create(['name' => 'Fremd', 'slug' => 'import-fremd-ui']);
    $fremdArtikel = Artikel::create([
        'tenant_id' => $fremdTenant->id,
        'name' => 'Fremd-Artikel',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Verwaltung,
        'bestand' => 0,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'artikel2.csv',
        "Bezeichnung;Einheit\nZucker;kg\n",
    );

    $component = Livewire::test(Datenimport::class)
        ->set('datei', $file)
        ->call('parsen')
        ->assertHasNoErrors();

    $zeile = ImportZeile::first();

    $component
        ->set("ist.{$zeile->id}.aktion", 'mergen')
        ->set("ist.{$zeile->id}.matched_artikel_id", $fremdArtikel->id)
        ->call('bestaetigeZeile', $zeile->id)
        ->assertHasErrors(["ist.{$zeile->id}.matched_artikel_id"]);

    unset($file);
});

// --- Regressionstests Final-Review ---

it('A1: bestaetigeAlle importiert gültige Zeile und sammelt Fehler bei name-leerer Zeile (Schleife bricht nicht ab)', function () {
    $this->actingAs($this->user);

    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'bulk.csv',
        'anfangsbestand_modus' => 'ebk',
        'status' => 'offen',
        'erstellt_von' => $this->user->id,
    ]);

    $zeileOhneNamen = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => null,
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $zeileGueltig = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Verbandsmaterial',
        'einheit' => 'Stück',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    Livewire::test(Datenimport::class)
        ->set('batchId', $batch->id)
        ->call('bestaetigeAlle')
        ->assertHasErrors(["ist.{$zeileOhneNamen->id}.bestand"]);

    // Gültige Zeile wurde importiert
    expect(Artikel::where('tenant_id', $this->tenant->id)->where('name', 'Verbandsmaterial')->exists())->toBeTrue();

    // Fehlerhafte Zeile bleibt offen
    expect($zeileOhneNamen->fresh()->status)->toBe(ImportZeileStatus::Vorgeschlagen);
});

it('UX: direkter Aufruf zeigt jüngsten offenen Batch', function () {
    $this->actingAs($this->user);

    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'demo-import.csv',
        'anfangsbestand_modus' => 'ebk',
        'status' => 'offen',
        'erstellt_von' => $this->user->id,
    ]);

    ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Testprodukt',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    Livewire::test(Datenimport::class)
        ->assertSee('demo-import.csv');
});
