<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Import\Models\ImportZeile;
use App\Domains\Import\Services\ImportMatching;

beforeEach(function () {
    config(['speech.fake' => true]);

    $this->tenant = Tenant::create(['name' => 'Matching-Test', 'slug' => 'matching-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->batch = ImportBatch::create([
        'tenant_id' => $this->tenant->id,
        'anfangsbestand_modus' => 'ebk',
        'status' => 'offen',
    ]);

    $this->service = new ImportMatching(app(ArtikelMatcher::class));
});

it('schlägt Anlegen vor wenn kein Artikel passt', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Brandneuer Artikel',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->fuerZeile($zeile, $this->tenant->id);

    expect($zeile->aktion)->toBe(ImportAktion::Anlegen)
        ->and($zeile->matched_artikel_id)->toBeNull()
        ->and($zeile->status)->toBe(ImportZeileStatus::Vorgeschlagen);
});

it('schlägt Mergen vor wenn Artikel per Substring gefunden wird', function () {
    $artikel = Artikel::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Weizenmehl Type 405',
        'einheit' => 'kg',
        'abteilung' => Abteilung::Kueche,
        'bestand' => 0,
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Weizenmehl Type 405',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->fuerZeile($zeile, $this->tenant->id);

    expect($zeile->aktion)->toBe(ImportAktion::Mergen)
        ->and($zeile->matched_artikel_id)->toBe($artikel->id)
        ->and($zeile->kandidaten)->not->toBeEmpty()
        ->and($zeile->status)->toBe(ImportZeileStatus::Vorgeschlagen);
});

it('befüllt kandidaten auch beim Anlegen-Fall', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'artikel',
        'name' => 'Völlig unbekannt',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->fuerZeile($zeile, $this->tenant->id);

    expect($zeile->kandidaten)->toBeArray();
});

it('schlägt Mergen vor für Lieferant-Zeile wenn Lieferant existiert', function () {
    $lief = \App\Domains\Accounting\Models\Lieferant::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Metro Cash',
    ]);

    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'lieferant',
        'name' => 'Metro Cash',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->fuerZeile($zeile, $this->tenant->id);

    expect($zeile->aktion)->toBe(ImportAktion::Mergen)
        ->and($zeile->matched_lieferant_id)->toBe($lief->id);
});

it('schlägt Anlegen vor für Lieferant-Zeile wenn kein Lieferant passt', function () {
    $zeile = ImportZeile::create([
        'tenant_id' => $this->tenant->id,
        'batch_id' => $this->batch->id,
        'ziel_typ' => 'lieferant',
        'name' => 'Unbekannter Lieferant GmbH',
        'aktion' => ImportAktion::Anlegen,
        'status' => ImportZeileStatus::Vorgeschlagen,
    ]);

    $this->service->fuerZeile($zeile, $this->tenant->id);

    expect($zeile->aktion)->toBe(ImportAktion::Anlegen)
        ->and($zeile->matched_lieferant_id)->toBeNull();
});
