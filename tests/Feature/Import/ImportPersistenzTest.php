<?php

use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Import\Models\ImportZeile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');

    $this->tenant = Tenant::create(['name' => 'Import-Test', 'slug' => 'import-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
});

// ─── Enum-Labels ─────────────────────────────────────────────────────────────

it('ImportAktion-Enum hat korrekte Labels', function () {
    expect(ImportAktion::Anlegen->label())->toBe('Anlegen')
        ->and(ImportAktion::Mergen->label())->toBe('Mergen')
        ->and(ImportAktion::Ueberspringen->label())->toBe('Überspringen');
});

it('ImportZeileStatus-Enum hat korrekte Labels', function () {
    expect(ImportZeileStatus::Vorgeschlagen->label())->toBe('Vorgeschlagen')
        ->and(ImportZeileStatus::Importiert->label())->toBe('Importiert')
        ->and(ImportZeileStatus::Uebersprungen->label())->toBe('Übersprungen');
});

// ─── ImportBatch anlegen ──────────────────────────────────────────────────────

it('legt ImportBatch an und liest mapping-Cast korrekt aus', function () {
    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'stammdaten.csv',
        'anfangsbestand_modus' => 'ebk',
        'mapping' => ['name' => 'Bezeichnung', 'einheit' => 'Einheit'],
        'status' => 'offen',
    ]);

    $fresh = ImportBatch::find($batch->id);

    expect($fresh->dateiname)->toBe('stammdaten.csv')
        ->and($fresh->anfangsbestand_modus)->toBe('ebk')
        ->and($fresh->mapping)->toBe(['name' => 'Bezeichnung', 'einheit' => 'Einheit'])
        ->and($fresh->status)->toBe('offen');
});

// ─── ImportZeile anlegen + Casts ──────────────────────────────────────────────

it('legt ImportZeile an mit Enum-Casts und Array-Casts korrekt', function () {
    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'test.csv',
        'status' => 'offen',
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'roh' => ['Bezeichnung' => 'Mehl', 'Einheit' => 'kg'],
        'ziel_typ' => 'artikel',
        'name' => 'Mehl',
        'einheit' => 'kg',
        'einkaufspreis' => '2.50',
        'bestand' => '10.00',
        'einstandspreis' => '2.4999',
        'kandidaten' => [['id' => 1, 'name' => 'Mehl alt']],
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $fresh = ImportZeile::find($zeile->id);

    expect($fresh->roh)->toBe(['Bezeichnung' => 'Mehl', 'Einheit' => 'kg'])
        ->and($fresh->kandidaten)->toBe([['id' => 1, 'name' => 'Mehl alt']])
        ->and($fresh->aktion)->toBe(ImportAktion::Anlegen)
        ->and($fresh->status)->toBe(ImportZeileStatus::Vorgeschlagen)
        ->and((float) $fresh->einkaufspreis)->toBe(2.50)
        ->and((float) $fresh->einstandspreis)->toBe(2.4999);
});

// ─── offen() ─────────────────────────────────────────────────────────────────

it('offen() gibt true zurück für Vorgeschlagen', function () {
    $batch = ImportBatch::create(['tenant_id' => $this->tenant->id, 'status' => 'offen']);
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'status' => ImportZeileStatus::Vorgeschlagen,
        'aktion' => ImportAktion::Anlegen,
    ]);

    expect($zeile->offen())->toBeTrue();
});

it('offen() gibt false zurück für Importiert', function () {
    $batch = ImportBatch::create(['tenant_id' => $this->tenant->id, 'status' => 'offen']);
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'status' => ImportZeileStatus::Importiert,
        'aktion' => ImportAktion::Anlegen,
    ]);

    expect($zeile->offen())->toBeFalse();
});

it('offen() gibt false zurück für Uebersprungen', function () {
    $batch = ImportBatch::create(['tenant_id' => $this->tenant->id, 'status' => 'offen']);
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'status' => ImportZeileStatus::Uebersprungen,
        'aktion' => ImportAktion::Ueberspringen,
    ]);

    expect($zeile->offen())->toBeFalse();
});

// ─── Relation ─────────────────────────────────────────────────────────────────

it('ImportBatch->zeilen() Relation gibt ImportZeilen zurück', function () {
    $batch = ImportBatch::create(['tenant_id' => $this->tenant->id, 'status' => 'offen']);

    ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Artikel A',
        'status' => ImportZeileStatus::Vorgeschlagen,
        'aktion' => ImportAktion::Anlegen,
    ]);

    ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Artikel B',
        'status' => ImportZeileStatus::Vorgeschlagen,
        'aktion' => ImportAktion::Mergen,
    ]);

    expect($batch->zeilen()->count())->toBe(2);
});

// ─── Media-Download ───────────────────────────────────────────────────────────

it('ImportBatch-Media-Download gibt 200 für eigenen Mandanten zurück', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');

    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'import.csv',
        'status' => 'offen',
    ]);

    // WHY(GC-FALLE): Variable muss bis nach der Assertion leben.
    $csvFile = UploadedFile::fake()->create('import.csv', 10, 'text/csv');
    $media = $batch->addMedia($csvFile)->toMediaCollection('quelle');

    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk();

    unset($csvFile);
});

it('ImportBatch-Media-Download gibt 403 für fremden Mandanten (IDOR)', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');

    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'dateiname' => 'import_fremd.csv',
        'status' => 'offen',
    ]);

    // WHY(GC-FALLE): Variable muss bis nach der Assertion leben.
    $csvFile = UploadedFile::fake()->create('import_fremd.csv', 10, 'text/csv');
    $media = $batch->addMedia($csvFile)->toMediaCollection('quelle');

    $fremdTenant = Tenant::create(['name' => 'Import-Fremd', 'slug' => 'import-fremd']);
    AccountingDefaults::ensureFor($fremdTenant->id);
    $fremdUser = User::factory()->create(['tenant_id' => $fremdTenant->id]);
    $fremdUser->assignRole('buchhaltung');
    app(CurrentTenant::class)->set($fremdTenant);

    $url = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);

    $this->actingAs($fremdUser)
        ->get($url)
        ->assertForbidden();

    unset($csvFile);
});
