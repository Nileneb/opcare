<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Capture\Contracts\TextEmbedder;
use App\Domains\Capture\Models\LieferantArtikelAlias;
use App\Domains\Capture\Services\ArtikelEmbedder;
use App\Domains\Capture\Services\EmbeddingArtikelMatcher;
use App\Domains\Capture\Support\LieferantMatch;
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

// --- Regressionstests Final-Review ---

it('A2: leerer Normtext (Sonderzeichen) liefert leeres Ergebnis ohne Pseudo-Match auf Aliasse', function () {
    $mehl = artikelAnlegen('Weizenmehl Type 405', $this->tenant);

    // Alias anlegen, der via str_contains('', '') treffen würde
    LieferantArtikelAlias::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_id' => null,
        'norm_text' => TextNorm::norm('Weizenmehl Type 405'),
        'artikel_id' => $mehl->id,
        'treffer' => 5,
    ]);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));

    // Nur Sonderzeichen → norm === '' → muss [] zurückgeben
    expect($matcher->match('---', null, $this->tenant->id))->toBeEmpty();
    expect($matcher->match('   ', null, $this->tenant->id))->toBeEmpty();
    expect($matcher->match('!@#$%', null, $this->tenant->id))->toBeEmpty();
});

it('B1: starkes Embedding (cosine≈1.0) schlägt Substring-Alias eines anderen Artikels', function () {
    // Artikel A: hat Alias mit Substring-Match (Score 0.75)
    $artikelA = artikelAnlegen('Meersalz fein', $this->tenant);

    // Artikel B: kein Alias, aber identischer Text → FakeEmbedder → cosine 1.0 → Score 1.0
    $artikelB = artikelAnlegen('Weizenmehl Type 405', $this->tenant);

    // Embeddings setzen
    $embedder = app(ArtikelEmbedder::class);
    $embedder->aktualisiere($artikelA);
    $embedder->aktualisiere($artikelB);

    // Substring-Alias für Artikel A: 'weizenmehl' ⊂ 'weizenmehl type 405' → str_contains → 0.75
    LieferantArtikelAlias::create([
        'tenant_id' => $this->tenant->id,
        'lieferant_id' => null,
        'norm_text' => 'weizenmehl',
        'artikel_id' => $artikelA->id,
        'treffer' => 1,
    ]);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));
    // Suchtext = exakt Artikel-B-Name → FakeEmbedder gibt gleichen Vektor → cosine 1.0 → Score 1.0
    $kandidaten = $matcher->match('Weizenmehl Type 405', null, $this->tenant->id);

    expect($kandidaten)->not->toBeEmpty();
    // Artikel B (Embedding cosine 1.0) muss vor Artikel A (Substring 0.75) stehen
    expect($kandidaten[0]->artikel_id)->toBe($artikelB->id);
    expect($kandidaten[0]->quelle)->toBe('embedding');
    expect($kandidaten[0]->score)->toBeGreaterThan(0.75);
});

it('B2: merke mit leerem/Whitespace-Text legt keinen LieferantArtikelAlias an', function () {
    $artikel = artikelAnlegen('Verbandsmaterial', $this->tenant);

    $matcher = new EmbeddingArtikelMatcher(app(TextEmbedder::class));

    $vorher = LieferantArtikelAlias::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->count();

    $matcher->merke('   ', null, $this->tenant->id, $artikel->id);
    $matcher->merke('', null, $this->tenant->id, $artikel->id);
    $matcher->merke('---', null, $this->tenant->id, $artikel->id);

    // Sonderzeichen → norm === '' → nichts gespeichert
    expect(LieferantArtikelAlias::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->count()
    )->toBe($vorher);
});

it('C3: LieferantMatch::finde mit Whitespace-only-Text gibt null zurück', function () {
    // Lieferant anlegen — würde bei norm==='' via str_contains matchen
    Lieferant::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Irgendein Lieferant',
    ]);

    expect(LieferantMatch::finde('   ', $this->tenant->id))->toBeNull();
    expect(LieferantMatch::finde("\t\n", $this->tenant->id))->toBeNull();
    expect(LieferantMatch::finde('---', $this->tenant->id))->toBeNull();
});
