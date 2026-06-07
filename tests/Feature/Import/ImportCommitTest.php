<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Import\Models\ImportZeile;
use App\Domains\Import\Services\ImportCommit;

beforeEach(function () {
    config(['speech.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'Commit-Test', 'slug' => 'commit-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'anfangsbestand_modus' => 'ebk',
        'status' => 'offen',
    ]);

    $this->service = new ImportCommit(app(ArtikelMatcher::class));
});

it('commit Lieferant anlegen legt neuen Lieferanten an', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'lieferant',
        'name' => 'Neuer Lieferant GmbH',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->status)->toBe(ImportZeileStatus::Importiert)
        ->and($result->ergebnis_lieferant_id)->not->toBeNull();

    $lief = Lieferant::find($result->ergebnis_lieferant_id);
    expect($lief)->not->toBeNull()
        ->and($lief->name)->toBe('Neuer Lieferant GmbH');
});

it('commit Artikel anlegen legt neuen Artikel an ohne Buchung wenn bestand=0', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Handschuhe L',
        'einheit' => 'Box',
        'abteilung' => Abteilung::Pflege->value,
        'einkaufspreis' => 3.50,
        'bestand' => 0,
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->status)->toBe(ImportZeileStatus::Importiert)
        ->and($result->ergebnis_artikel_id)->not->toBeNull()
        ->and($result->wareneingang_bewegung_id)->toBeNull();

    $artikel = Artikel::find($result->ergebnis_artikel_id);
    expect($artikel->name)->toBe('Handschuhe L')
        ->and($artikel->einheit)->toBe('Box');
});

it('commit Anfangsbestand EBK bucht auf Konto 9000', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Mehl Type 550',
        'einheit' => 'kg',
        'abteilung' => Abteilung::Kueche->value,
        'bestand' => 50,
        'einstandspreis' => 2.00,
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->status)->toBe(ImportZeileStatus::Importiert)
        ->and($result->wareneingang_bewegung_id)->not->toBeNull();

    $artikel = Artikel::find($result->ergebnis_artikel_id);
    expect((float) $artikel->bestand)->toBe(50.0);

    $schicht = Lagerschicht::where('artikel_id', $artikel->id)->first();
    expect($schicht)->not->toBeNull()
        ->and((float) $schicht->menge_rest)->toBe(50.0);

    expect(AccountingDefaults::konto(AccountingDefaults::ANFANGSBESTAND)->saldo())->toBe(100.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN)->saldo())->toBe(0.0);
});

it('commit Anfangsbestand Verbindlichkeit bucht auf 1600 nicht 9000', function () {
    $batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'anfangsbestand_modus' => 'verbindlichkeit',
        'status' => 'offen',
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Butter',
        'einheit' => 'kg',
        'abteilung' => Abteilung::Kueche->value,
        'bestand' => 20,
        'einstandspreis' => 4.00,
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->wareneingang_bewegung_id)->not->toBeNull();
    expect(AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN)->saldo())->toBe(80.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::ANFANGSBESTAND)->saldo())->toBe(0.0);
});

it('commit mergen überschreibt keine bestehenden Felder und ergänzt leere', function () {
    $artikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Olivenöl Extra Vergine',
        'einheit' => 'L',
        'abteilung' => Abteilung::Kueche,
        'bestand' => 5,
        'einkaufspreis' => 5.00,
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Olivenöl Extra Vergine',
        'einkaufspreis' => 9.99,
        'pg_nummer' => '54.1',
        'bestand' => 0,
        'matched_artikel_id' => $artikel->id,
        'aktion' => ImportAktion::Mergen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->status)->toBe(ImportZeileStatus::Importiert)
        ->and($result->ergebnis_artikel_id)->toBe($artikel->id);

    $frisch = Artikel::find($artikel->id);
    expect((float) $frisch->einkaufspreis)->toBe(5.00)
        ->and($frisch->pg_nummer)->toBe('54.1');

    expect(Artikel::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('re-import-dedup: nach Mergen-Commit findet Matcher den Alias', function () {
    $artikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kartoffeln festkochend',
        'einheit' => 'kg',
        'abteilung' => Abteilung::Kueche,
        'bestand' => 0,
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Kartoffeln festkochend',
        'bestand' => 0,
        'matched_artikel_id' => $artikel->id,
        'aktion' => ImportAktion::Mergen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->commit($zeile, $this->tenant->id);

    $matcher = app(ArtikelMatcher::class);
    $kandidaten = $matcher->match('Kartoffeln festkochend', null, $this->tenant->id);

    expect($kandidaten)->not->toBeEmpty()
        ->and($kandidaten[0]->artikel_id)->toBe($artikel->id);
});

it('commit auf nicht-offener Zeile wirft Exception', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Test',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Importiert,
    ]);

    expect(fn () => $this->service->commit($zeile, $this->tenant->id))
        ->toThrow(RuntimeException::class);
});

it('commit Ueberspringen setzt Status Uebersprungen', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Ignorieren',
        'aktion' => ImportAktion::Ueberspringen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $result = $this->service->commit($zeile, $this->tenant->id);

    expect($result->status)->toBe(ImportZeileStatus::Uebersprungen);
});
