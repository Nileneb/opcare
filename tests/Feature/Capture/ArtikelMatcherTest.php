<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Capture\Contracts\TextEmbedder;
use App\Domains\Capture\Models\LieferantArtikelAlias;
use App\Domains\Capture\Services\ArtikelEmbedder;
use App\Domains\Capture\Services\EmbeddingArtikelMatcher;
use App\Domains\Capture\Support\TextNorm;
use App\Domains\Capture\Testing\FakeTextEmbedder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'MatcherTenant', 'slug' => 'matcher-tenant']);
    app(CurrentTenant::class)->set($this->tenant);
    app()->instance(TextEmbedder::class, new FakeTextEmbedder);
});

function artikelAnlegen(string $name, Tenant $tenant): Artikel
{
    return Artikel::create([
        'name' => $name,
        'einheit' => 'kg',
        'abteilung' => Abteilung::Hauswirtschaft,
        'bestand' => 10,
        'aktiv' => true,
        'pflegehilfsmittel' => false,
        'gefahrstoff' => false,
        'tenant_id' => $tenant->id,
    ]);
}

it('TextNorm normalisiert korrekt', function () {
    expect(TextNorm::norm('Weizenmehl  TYPE-405!'))->toBe('weizenmehl type 405');
});

it('Gedächtnis schlägt Embedding: Alias → erster Kandidat ist Mehl, Score ≥ 1.0, quelle gedaechtnis', function () {
    $mehl = artikelAnlegen('Weizenmehl Type 405', $this->tenant);
    $zucker = artikelAnlegen('Zucker', $this->tenant);

    // Embeddings für beide setzen (damit Embedding-Pfad auch Kandidaten liefert)
    $embedder = app(ArtikelEmbedder::class);
    $embedder->aktualisiere($mehl);
    $embedder->aktualisiere($zucker);

    $lief = Lieferant::create([
        'name' => 'Bäcker GmbH',
        'tenant_id' => $this->tenant->id,
    ]);

    LieferantArtikelAlias::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_id' => $lief->id,
        'norm_text' => TextNorm::norm('Weizenmehl Type 405 25kg'),
        'artikel_id' => $mehl->id,
        'treffer' => 1,
    ]);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));
    $kandidaten = $matcher->match('Weizenmehl Type 405 25kg', $lief->id, $this->tenant->id);

    expect($kandidaten)->not->toBeEmpty();
    expect($kandidaten[0]->artikel_id)->toBe($mehl->id);
    expect($kandidaten[0]->score)->toBeGreaterThanOrEqual(1.0);
    expect($kandidaten[0]->quelle)->toBe('gedaechtnis');
});

it('Embedding-Pfad: ohne Alias, identischer Text → selber Artikel oben', function () {
    $mehl = artikelAnlegen('Weizenmehl Type 405', $this->tenant);
    $salz = artikelAnlegen('Meersalz fein', $this->tenant);

    $embedder = app(ArtikelEmbedder::class);
    $embedder->aktualisiere($mehl);
    $embedder->aktualisiere($salz);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));

    // Identischer Text → Cosine 1.0 → Score 0.6
    $kandidaten = $matcher->match('Weizenmehl Type 405', null, $this->tenant->id);

    expect($kandidaten)->not->toBeEmpty();
    expect($kandidaten[0]->artikel_id)->toBe($mehl->id);
    expect($kandidaten[0]->quelle)->toBe('embedding');
});

it('Null-Embedder ohne Alias → leeres Ergebnis, keine Exception', function () {
    artikelAnlegen('Weizenmehl Type 405', $this->tenant);

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

    $matcher = new EmbeddingArtikelMatcher($nullEmbedder);

    expect(fn () => $matcher->match('Weizenmehl Type 405 25kg', null, $this->tenant->id))->not->toThrow(Throwable::class);

    $kandidaten = $matcher->match('Weizenmehl Type 405 25kg', null, $this->tenant->id);
    expect($kandidaten)->toBeEmpty();
});

it('Null-Embedder mit Alias → nur Gedächtnis-Treffer, kein Fehler', function () {
    $mehl = artikelAnlegen('Weizenmehl Type 405', $this->tenant);

    LieferantArtikelAlias::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_id' => null,
        'norm_text' => TextNorm::norm('Weizenmehl Type 405'),
        'artikel_id' => $mehl->id,
        'treffer' => 1,
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

    $matcher = new EmbeddingArtikelMatcher($nullEmbedder);
    $kandidaten = $matcher->match('Weizenmehl Type 405', null, $this->tenant->id);

    expect($kandidaten)->toHaveCount(1);
    expect($kandidaten[0]->artikel_id)->toBe($mehl->id);
    expect($kandidaten[0]->quelle)->toBe('gedaechtnis');
});

it('merke lernt: zweimal aufrufen → treffer == 2', function () {
    $mehl = artikelAnlegen('Weizenmehl Type 405', $this->tenant);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));
    $matcher->merke('Weizenmehl Type 405', null, $this->tenant->id, $mehl->id);
    $matcher->merke('Weizenmehl Type 405', null, $this->tenant->id, $mehl->id);

    $alias = LieferantArtikelAlias::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('artikel_id', $mehl->id)
        ->first();

    expect($alias->treffer)->toBe(2);
});
