<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Capture\Contracts\TextEmbedder;
use App\Domains\Capture\Services\ArtikelEmbedder;
use App\Domains\Capture\Testing\FakeTextEmbedder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'TestEmbedTenant', 'slug' => 'embed-tenant']);
    app(CurrentTenant::class)->set($this->tenant);
    app()->instance(TextEmbedder::class, new FakeTextEmbedder);
});

it('speichert einen 8-dimensionalen Float-Vektor im Artikel', function () {
    $artikel = Artikel::create([
        'name' => 'Einweg-Handschuhe L',
        'einheit' => 'Box',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 50,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $embedder = app(ArtikelEmbedder::class);
    $embedder->aktualisiere($artikel);

    $artikel->refresh();
    expect($artikel->name_embedding)->toBeArray()->toHaveCount(8)
        ->and($artikel->embedding_model)->toBe('fake-embed');

    foreach ($artikel->name_embedding as $wert) {
        expect($wert)->toBeFloat();
    }
});

it('lässt name_embedding null wenn der Embedder null zurückgibt', function () {
    $artikel = Artikel::create([
        'name' => 'Bettschutzeinlage',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 10,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
    ]);

    $nullEmbedder = new class implements TextEmbedder
    {
        public function embed(string $text): ?array
        {
            return null;
        }

        public function model(): string
        {
            return 'null-embed';
        }
    };

    app()->instance(TextEmbedder::class, $nullEmbedder);
    $embedder = app(ArtikelEmbedder::class);

    $embedder->aktualisiere($artikel);

    expect($artikel->fresh()->name_embedding)->toBeNull();
});

it('fehlt() ist true vor und false nach aktualisiere', function () {
    $artikel = Artikel::create([
        'name' => 'Desinfektionsmittel 1L',
        'einheit' => 'Flasche',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 5,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => true,
    ]);

    $embedder = app(ArtikelEmbedder::class);

    expect($embedder->fehlt($artikel))->toBeTrue();

    $embedder->aktualisiere($artikel);
    $artikel->refresh();

    expect($embedder->fehlt($artikel))->toBeFalse();
});
