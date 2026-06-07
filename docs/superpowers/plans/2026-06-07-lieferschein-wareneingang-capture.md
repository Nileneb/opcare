# Lieferschein → Wareneingang Capture — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. TDD je Task, volle Suite als Gate zwischen Tasks.

**Goal:** Foto eines Lieferscheins → VLM extrahiert Positionen → lokales Embedding-Artikel-Matching → bestätigter FIFO-Wareneingang (standalone oder gegen offene Bestellung).

**Architecture:** Erweitert die `Capture`-Domäne. VLM via Ollama (Provider-Pattern wie `BelegVlmAnalyzer`). Artikel-Matching lokal (Match-Gedächtnis + Ollama-Embedding-Cosine in PHP, kein pgvector). Buchung über die bestehende FIFO-Spine (`Wareneingang`/`BestellungWareneingang`). HITL pro Position. Null Pflegedaten.

**Tech Stack:** Laravel 13, Livewire 4, spatie/laravel-data, Spatie MediaLibrary, Ollama, Pest, Larastan L5, Pint.

**Spec:** `docs/superpowers/specs/2026-06-07-lieferschein-wareneingang-capture-design.md`.

**Konventionen (verbindlich):** BaseModel=BelongsToTenant+LogsActivity; Append-/mutierende Modelle nur `BelongsToTenant`. Tests: `Model::create`, `app(CurrentTenant::class)->set($t)`, `AccountingDefaults::ensureFor($t->id)`, `Role::findOrCreate`. tenant-scoped exists via `$this->tenantExists('tabelle')` (kein nacktes `exists:`). Media-Download signiert + Owner-Whitelist im `MediaDownloadController` ([[opcare-media-download-owner-pattern]]). Kein stilles Schlucken. ide-helper positional (`-W -R "FQCN"`, kein `-M`). Gates: `php -d memory_limit=1G vendor/bin/phpstan analyse`, `vendor/bin/pint`, `php -d memory_limit=1G vendor/bin/pest`. **Vorbild der ganzen Pipeline:** `app/Domains/Capture/{Contracts/BelegVlmAnalyzer.php, Data/BelegExtraktion.php, Providers/CaptureServiceProvider.php, Testing/FakeBelegAnalyzer.php, Services/BelegCapture.php}` + `app/Livewire/Capture/Belegerfassung.php`.

Branch: `feat/lieferschein-wareneingang-capture` (anlegen, am Ende `--no-ff` nach master + push).

---

## Task 1: Lieferschein-VLM-Extraktion (Contract + DTOs + Analyzer + Fake)

**Files:**
- Create: `app/Domains/Capture/Data/LieferscheinPositionDaten.php`, `app/Domains/Capture/Data/LieferscheinExtraktion.php`
- Create: `app/Domains/Capture/Contracts/LieferscheinVlmAnalyzer.php`
- Create: `app/Domains/Capture/Providers/OllamaLieferscheinAnalyzer.php`, `app/Domains/Capture/Testing/FakeLieferscheinAnalyzer.php`
- Modify: `app/Domains/Capture/Providers/CaptureServiceProvider.php` (Binding)
- Test: `tests/Feature/Capture/LieferscheinExtraktionTest.php`

**Contract:** Lies zuerst die Beleg-Pendants (`BelegVlmAnalyzer`, `BelegExtraktion`, `OllamaBelegAnalyzer`, `FakeBelegAnalyzer`) und spiegele Stil/Struktur exakt.
- `LieferscheinPositionDaten` (Spatie `Data`): `string $text`, `?float $menge`, `?string $einheit`, `?float $einzelpreis`, `?string $charge_nr`, `?string $mhd`.
- `LieferscheinExtraktion` (Spatie `Data`): `?string $lieferant`, `?string $datum`, `?string $lieferschein_nr`, `float $konfidenz`, `/** @var LieferscheinPositionDaten[] */ array $positionen` + statische `from(array $roh): self` (normalisiert wie `BelegExtraktion::from`).
- `LieferscheinVlmAnalyzer` Interface: `analysiere(string $imageBase64, string $mimeType): LieferscheinExtraktion`.
- `OllamaLieferscheinAnalyzer implements LieferscheinVlmAnalyzer`: POST `{config('speech.ollama.url')}/api/generate`, Modell `config('speech.capture.model')`, `format=json`, `stream=false`, `think=false`; Prompt fordert strukturierte Positionszeilen (text, menge, einheit, einzelpreis, charge, mhd) + lieferant/datum/lieferschein_nr; `normalisiere()` → `LieferscheinExtraktion::from`.
- `FakeLieferscheinAnalyzer implements LieferscheinVlmAnalyzer`: liefert deterministisch z. B. Lieferant „Großhandel Bergisch GmbH", 2 Positionen („Weizenmehl Type 405 25kg", menge 10, einheit „Sack"; „Markenbutter 250g", menge 40, einheit „Stück", charge „CH-A1", mhd in 20 Tagen), konfidenz 0.9.
- Binding im `CaptureServiceProvider`: `$this->app->bind(LieferscheinVlmAnalyzer::class, fn () => config('speech.fake') ? new FakeLieferscheinAnalyzer : new OllamaLieferscheinAnalyzer(...))` analog zum Beleg-Binding.

**Tests:** `config(['speech.fake'=>true])`; `app(LieferscheinVlmAnalyzer::class)->analysiere('x','image/jpeg')` → 2 Positionen mit menge/einheit, lieferant gesetzt, charge/mhd auf Pos 2.

**Steps:** (1) Test rot. (2) DTOs+Contract+Fake+Binding → grün. (3) OllamaLieferscheinAnalyzer (Struktur wie OllamaBelegAnalyzer, kein echter HTTP-Test nötig). (4) pint/phpstan/Suite. (5) Commit `feat(capture): Lieferschein-VLM-Extraktion (Contract, DTOs, Ollama+Fake-Analyzer)`.

---

## Task 2: Artikel-Embeddings (Migration + ArtikelEmbedder + Backfill-Command)

**Files:**
- Migrate: `…_add_embedding_to_artikel.php` (`artikel.name_embedding` json nullable, `artikel.embedding_model` string nullable)
- Modify: `app/Domains/Accounting/Models/Artikel.php` (fillable+cast `name_embedding=>'array'`+@property)
- Create: `app/Domains/Capture/Contracts/TextEmbedder.php`, `app/Domains/Capture/Providers/OllamaTextEmbedder.php`, `app/Domains/Capture/Testing/FakeTextEmbedder.php`
- Create: `app/Domains/Capture/Services/ArtikelEmbedder.php`
- Create: `app/Console/Commands/ArtikelEmbeddingsBackfill.php` (Signatur `artikel:embeddings-backfill`)
- Modify: `CaptureServiceProvider` (Binding `TextEmbedder`)
- Test: `tests/Feature/Capture/ArtikelEmbedderTest.php`

**Contract:**
- `TextEmbedder` Interface: `embed(string $text): ?array` (Vektor float[]) + `model(): string`. Null bei nicht verfügbar.
- `OllamaTextEmbedder`: POST `{config('speech.ollama.url')}/api/embeddings` mit `model=config('speech.capture.embedding_model')` (Default in `config/speech.php` ergänzen: `'embedding_model' => env('CAPTURE_EMBEDDING_MODEL','nomic-embed-text')`), gibt `embedding`-Array zurück; bei Fehler/404 → `null` (KEIN Throw-Swallow: Fehler loggen via `report()`/Log, dann null als „nicht verfügbar"-Signal — das ist KEIN maskierter Bug, sondern ein definierter Zustand).
- `FakeTextEmbedder`: deterministischer Vektor aus `crc32`-Hash des Textes (feste Dim 8), `model()='fake-embed'`.
- `ArtikelEmbedder::aktualisiere(Artikel $a): void`: `$vec = $embedder->embed($a->name)`; wenn null → nichts (Log), sonst `$a->update(['name_embedding'=>$vec,'embedding_model'=>$embedder->model()])`.
- Command `artikel:embeddings-backfill`: alle Artikel des aktuellen/aller Tenants ohne aktuelles Embedding → `aktualisiere`. Idempotent.

**Tests:** Fake-Embedder; `aktualisiere($artikel)` setzt `name_embedding` (8 floats) + `embedding_model='fake-embed'`. Mit einem null-liefernden Embedder (eigener Stub) → kein Embedding gesetzt, kein Fehler geworfen.

**Steps:** (1) Test rot. (2) Migration+Model+TextEmbedder(Fake)+ArtikelEmbedder → grün. (3) OllamaTextEmbedder + config-Default + Command. (4) ide-helper Artikel, pint/phpstan/Suite. (5) Commit `feat(capture): Artikel-Namen-Embeddings (Ollama TextEmbedder + Backfill, DSGVO-lokal)`.

---

## Task 3: ArtikelMatcher (Interface + Match-Gedächtnis + Embedding-Cosine)

**Files:**
- Migrate: `…_create_lieferant_artikel_aliasse_table.php` (`tenant_id` FK, `lieferant_id` nullable FK nullOnDelete, `norm_text` string, `artikel_id` FK cascade, `treffer` unsignedInteger default 1, timestamps, unique[`tenant_id`,`lieferant_id`,`norm_text`,`artikel_id`])
- Create: `app/Domains/Capture/Models/LieferantArtikelAlias.php` (BelongsToTenant only — Lern-Log)
- Create: `app/Domains/Capture/Data/ArtikelKandidat.php` (`int $artikel_id`, `string $name`, `float $score`, `string $quelle`)
- Create: `app/Domains/Capture/Contracts/ArtikelMatcher.php`, `app/Domains/Capture/Services/EmbeddingArtikelMatcher.php`, `app/Domains/Capture/Testing/FakeArtikelMatcher.php`
- Create: `app/Domains/Capture/Support/TextNorm.php` (`norm(string): string` — lowercase, trim, Mehrfach-Whitespace, Sonderzeichen weg)
- Modify: `CaptureServiceProvider` (Binding `ArtikelMatcher`)
- Test: `tests/Feature/Capture/ArtikelMatcherTest.php`

**Contract:**
- `ArtikelMatcher` Interface: `match(string $positionsText, ?int $lieferantId, int $tenantId, int $topK = 5): array` (`ArtikelKandidat[]`, score-desc) + `merke(string $positionsText, ?int $lieferantId, int $tenantId, int $artikelId): void` (Gedächtnis-Upsert).
- `EmbeddingArtikelMatcher` (nutzt `TextEmbedder`):
  - **Primär (Gedächtnis):** `LieferantArtikelAlias` mit `tenant_id` + (lieferant_id == oder null) + `norm_text` == norm(positionsText) → Score `1.0` (+ kleiner Bonus je `treffer`, gedeckelt). Auch Teilstring-Treffer (norm enthält/enthalten) → Score `0.8`.
  - **Sekundär (Embedding):** `$q = $embedder->embed($positionsText)`; wenn null → **überspringen** (nur Gedächtnis, Log „embedding skipped"). Sonst Cosine gegen alle `artikel.name_embedding != null` des Tenants (Brute-Force PHP), Score `cosine*0.6`, nur wenn `cosine >= 0.5`.
  - Merge je artikel_id (max Score), top-k. `quelle` = stärkeres Signal.
  - `merke`: `LieferantArtikelAlias::updateOrCreate([tenant,lieferant,norm_text,artikel],[ ])` → bei Existenz `increment('treffer')`.
- `FakeArtikelMatcher`: deterministisch — matcht per Substring des `artikel.name` gegen positionsText, Score 1.0; `merke` no-op oder einfache Sammlung.
- `TextNorm::norm`: `Str::lower(trim(preg_replace('/\s+/',' ', preg_replace('/[^\p{L}\p{N}\s]/u',' ', $s))))`.

**Tests (Fake-Embedder als TextEmbedder):**
- Gedächtnis schlägt Embedding: Alias (lieferant X, „weizenmehl type 405" → Artikel Mehl) gesetzt → `match('Weizenmehl Type 405 25kg', X, t)` liefert Mehl mit Score≈1.0/quelle gedaechtnis, vor anderen.
- Embedding-Pfad: ohne Alias, mit `name_embedding` auf Artikeln (via ArtikelEmbedder/Fake) → ähnlichster Artikel oben.
- Null-Embedder (Stub `embed`→null): nur Gedächtnis-Treffer, KEIN Fehler, KEIN gefälschter Embedding-Treffer.
- `merke` legt Alias an / inkrementiert `treffer` beim zweiten Mal.

**Steps:** (1) TextNorm-Test + Matcher-Tests rot. (2) Migration+Model+DTO+TextNorm+Matcher(+Fake)+Binding → grün. (3) ide-helper, pint/phpstan/Suite. (4) Commit `feat(capture): ArtikelMatcher — Match-Gedächtnis + lokales Embedding-Cosine`.

---

## Task 4: Vorschlags-Persistenz (Analyse + Position + Status + Media-Owner)

**Files:**
- Create: `app/Domains/Capture/Enums/PositionStatus.php` (`Vorgeschlagen`/`Bestaetigt`/`Verworfen` + `label()`)
- Migrate: `…_create_lieferschein_analysen_table.php`, `…_create_lieferschein_position_vorschlaege_table.php`
- Create: `app/Domains/Capture/Models/LieferscheinAnalyse.php` (BaseModel, `implements HasMedia`, Collection `lieferschein`), `app/Domains/Capture/Models/LieferscheinPositionVorschlag.php` (BaseModel)
- Modify: `app/Http/Controllers/MediaDownloadController.php` (Owner-Whitelist um `LieferscheinAnalyse`, tenant-scoped — exakt das `match`-Muster aus dem Gefahrstoff-Fix)
- Test: `tests/Feature/Capture/LieferscheinPersistenzTest.php`

**Schemas:**
- `lieferschein_analysen`: `tenant_id` FK, `lieferant_text` nullable, `lieferant_id` nullable FK(lieferanten) nullOnDelete, `datum` date nullable, `lieferschein_nr` string nullable, `roh_json` json nullable, `modell` string nullable, `konfidenz` decimal(4,3) nullable, `erstellt_von` nullable FK(users) nullOnDelete, timestamps.
- `lieferschein_position_vorschlaege`: `tenant_id` FK, `analyse_id` FK(lieferschein_analysen) cascade, `text` string, `menge` decimal(12,2) nullable, `einheit` string nullable, `einzelpreis` decimal(12,2) nullable, `charge_nr` string nullable, `mhd` date nullable, `matched_artikel_id` nullable FK(artikel) nullOnDelete, `matched_bestellposition_id` nullable FK(bestellpositionen) nullOnDelete, `kandidaten` json nullable, `konfidenz` decimal(4,3) nullable, `status` string default 'vorgeschlagen', `wareneingang_bewegung_id` nullable FK(lagerbewegungen) nullOnDelete, `entschieden_von` nullable FK(users) nullOnDelete, `entschieden_am` timestamp nullable, timestamps.
- Models: casts (`datum`/`mhd` date, `kandidaten`/`roh_json` array, `status`=>PositionStatus, mengen decimal). `LieferscheinAnalyse`: `positionen()` HasMany, `registerMediaCollections()` Collection `lieferschein` (`useDisk(config('opcare.media_disk','media'))`), `artikel`/`lieferant`-Relationen wo sinnvoll. `LieferscheinPositionVorschlag`: `analyse()`, `artikel()`, `bestellposition()`-Relationen; `offen(): bool` (status Vorgeschlagen).

**Tests:** Analyse + 2 Positionen anlegen, array-Casts (`kandidaten`/`roh_json`) round-trip; SDB-äquivalenter Media-Download-Test: Foto an `LieferscheinAnalyse` hängen (`UploadedFile::fake()->create('ls.jpg',100,'image/jpeg')` — GC-Falle: Variable halten), signierte URL `URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media'=>$media->id])` → 200 für eigenen Tenant, 403 für fremden.

**Steps:** (1) Tests rot. (2) Enum+Migrations+Models+Controller-Owner → grün. (3) ide-helper beide Modelle, pint/phpstan/Suite. (4) Commit `feat(capture): Lieferschein-Vorschlags-Persistenz + Media-Owner (signiert, tenant-scoped)`.

---

## Task 5: Capture-Service (Erfassen + Buchen + Lernen + Bestell-Abgleich)

**Files:**
- Create: `app/Domains/Capture/Services/CaptureWareneingang.php`
- Create: `app/Domains/Capture/Support/LieferantMatch.php` (`finde(string $text, int $tenantId): ?Lieferant` — norm Exakt/Teilstring)
- Test: `tests/Feature/Capture/CaptureWareneingangTest.php`

**Contract:**
- `erfasse(string $imageBase64, string $mimeType, ?int $userId): LieferscheinAnalyse` (DB::transaction):
  1. `$ext = $analyzer->analysiere(...)`.
  2. `LieferscheinAnalyse` anlegen (Felder aus `$ext`, `roh_json`, `modell`=config, `konfidenz`, `erstellt_von`, `lieferant_id` via `LieferantMatch::finde($ext->lieferant)`).
  3. Foto via `addMediaFromString(base64_decode($imageBase64))->usingFileName(...)->toMediaCollection('lieferschein')`.
  4. Je `$ext->positionen`: `$kand = $matcher->match($pos->text, $analyse->lieferant_id, $tenantId)`; bestes Match → `matched_artikel_id`; offene Bestellposition suchen (siehe unten) → `matched_bestellposition_id`; `LieferscheinPositionVorschlag` mit Feldern + `kandidaten` (top-k als array) + `konfidenz` anlegen.
- Offene-Bestellposition-Suche: wenn `lieferant_id` und `matched_artikel_id` gesetzt → `Bestellposition` join `Bestellung` where `bestellung.lieferant_id == lieferant_id`, `artikel_id == matched_artikel_id`, `offen()`; **genau ein** Treffer → vorbelegen, sonst null.
- `bestaetige(LieferscheinPositionVorschlag $p, int $artikelId, float $menge, ?float $preis, ?string $chargeNr, ?string $mhd, ?int $bestellpositionId, ?int $userId): LieferscheinPositionVorschlag` (DB::transaction):
  - `abort_unless($artikelId > 0)`; guard `$p->offen()`.
  - wenn `$bestellpositionId` → `app(BestellungWareneingang::class)->handle($pos, $menge, $preis, today, $chargeNr, $mhd)` (Exceptions propagieren — der Livewire-Caller fängt sie); sonst `app(Wareneingang::class)->handle($artikel, $menge, $preis, today, 'Lieferschein-Capture', $chargeNr, $mhd, $p->analyse->lieferant_id)`.
  - `$p->update([matched_artikel_id=>artikelId, wareneingang_bewegung_id=>bewegung->id, status=>Bestaetigt, entschieden_von, entschieden_am=>now])`.
  - `$matcher->merke($p->text, $p->analyse->lieferant_id, $tenantId, $artikelId)`.
- `verwerfe(LieferscheinPositionVorschlag $p, ?int $userId): void` (status Verworfen, entschieden_*).

**Tests (`speech.fake`=true, FakeArtikelMatcher):** Artikel „Weizenmehl Type 405" + „Markenbutter 250g" anlegen (mit Substring-matchbaren Namen) →
- `erfasse(...)` legt Analyse + 2 Positionen an, beide mit `matched_artikel_id` (Fake-Substring), Foto in Collection.
- `bestaetige` standalone → FIFO `Lagerschicht` entsteht (lieferant_id, charge/mhd), Bestand steigt, Position `Bestaetigt`, `wareneingang_bewegung_id` gesetzt, Alias gelernt.
- `bestaetige` gegen offene Bestellposition (Bestellung via `BestellungAnlegen` vorab) → `menge_geliefert` steigt, Schicht trägt `bestellposition_id`.
- Über-Lieferung gegen Bestellposition → InvalidArgumentException.
- `bestaetige` mit artikelId 0 → abort/Exception (kein Raten).
- Gelernt: nach `bestaetige` matcht `match(text, lieferant)` denselben Artikel über das Gedächtnis (auch mit null-Embedder).

**Steps:** (1) Tests rot. (2) LieferantMatch+CaptureWareneingang → grün. (3) pint/phpstan/Suite. (4) Commit `feat(capture): CaptureWareneingang-Service — erfassen, buchen (standalone/Bestellung), Gedächtnis lernt`.

---

## Task 6: Livewire-UI (Wareneingangerfassung)

**Files:**
- Create: `app/Livewire/Capture/Wareneingangerfassung.php` + `resources/views/livewire/capture/wareneingangerfassung.blade.php`
- Modify: `routes/web.php` (Route `/wareneingang-capture` Name `wareneingang-capture`), `resources/views/layouts/app.blade.php` (Nav „Beleg→Wareneingang" im Finanzen-Block, Gate admin/buchhaltung)
- Test: `tests/Feature/Capture/WareneingangerfassungTest.php`

**Contract:** `#[Layout('layouts.app')]`, `use WithFileUploads;`, `use ScopesTenantValidation;`. Properties: `$foto` (Upload), aktuelle `$analyseId`, Editier-State je Position (`$ist` array: positionId → [artikel_id, menge, preis, charge, mhd, bestellposition_id]). Gate `darf()` = admin/buchhaltung, `abort_unless($this->darf(),403)` in jeder Aktion.
- `analysieren(CaptureWareneingang $svc)`: validate foto (`image`, max 8192), `$svc->erfasse(base64, mime, auth()->id())`, Reset foto.
- `bestaetige(int $positionId, CaptureWareneingang $svc)`: liest `$ist[$positionId]`, tenant-scoped Validierung (`tenantExists('artikel')`, menge gt:0, bestellposition optional `tenantExists('bestellpositionen')`), ruft `$svc->bestaetige(...)`; `try/catch(InvalidArgumentException)` → `addError`.
- `verwerfe(int $positionId, CaptureWareneingang $svc)`.
- `render()`: offene Analysen + Positionen mit Kandidaten-Dropdown (vorausgewählt bester), Artikelliste + offene Bestellpositionen je Lieferant (tenant-scoped), Foto-Vorschau (signierte URL).
- View: Upload-Karte, je Analyse eine Positions-Tabelle (Beleg-Text | Kandidaten-Select | Menge/Einheit | Charge/MHD | Buchungsziel-Select | Buchen/Verwerfen), Status-Badges (green/amber/gray). Hinweis-Kasten: „DSGVO: nur Waren-/Lieferantendaten — keine Bewohnerdaten."

**Tests:** Rolle buchhaltung (Role::findOrCreate) → `Livewire::test(...)->assertOk()`; fremde Rolle → 403. Upload (`UploadedFile::fake()->image('ls.jpg')`, GC-Falle) → `analysieren` legt Analyse+Positionen an (assertSee Beleg-Text). `bestaetige` bucht (Bestand steigt, Position Bestätigt). Fremd-Tenant-Artikel in `bestaetige` → Validierungsfehler.

**Steps:** (1) Tests rot. (2) Component+View+Route+Nav → grün. (3) pint/phpstan/Suite. (4) Commit `feat(capture): Wareneingangerfassung-UI (Foto→Positionen→bestätigter Wareneingang)`.

---

## Abschluss (nach Task 6)
- DemoSeeder: `config(['speech.fake'=>true])`-unabhängig — lege einen Demo-`LieferscheinAnalyse` + Positionen (eine bestätigt → Wareneingang, eine offen) für sichtbare Daten an; Artikel-Embeddings backfillen (Fake im Test-Env, echtes Modell prod). `migrate:fresh --seed` grün.
- Screenshot `/wareneingang-capture` (shots.mjs Route ergänzen, MFA-Flow, danach 2FA reset).
- README-Testzähler, `docs/`-Doku (`docs/lieferschein-capture.md`), Wiki-Seite + Screenshot, Memory.
- Opus-Final-Review (Tenant-Scope, kein stilles Schlucken, Embedding-Skip-Transparenz, Media-Owner, Outcome-Anker), Fixes, dann `--no-ff` Merge nach master + push.
