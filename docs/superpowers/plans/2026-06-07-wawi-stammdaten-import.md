# WaWi-Stammdaten-Datenimport — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. TDD je Task, volle Suite als Gate zwischen Tasks.

**Goal:** Datei-Upload (Artikel/Lieferanten/Anfangsbestand) → Parse → Index-Matching (anlegen/mergen) → editierbare HITL-Vorschau → Commit: Stammdaten + bewerteter Anfangsbestand (FIFO-Schicht).

**Architecture:** Neuer Bounded Context `app/Domains/Import` auf dem Matching-Index (`ArtikelMatcher`/`LieferantMatch` aus Capture) + FIFO-Spine (`Wareneingang`). opcare-nativ, prefilter nur als Muster.

**Tech Stack:** Laravel 13, Livewire 4, spatie/laravel-data, Spatie MediaLibrary, Pest, Larastan L5, Pint. Kein neues Composer-Paket (CSV via `fgetcsv`).

**Spec:** `docs/superpowers/specs/2026-06-07-wawi-stammdaten-import-design.md`.

**Konventionen (verbindlich):** BaseModel=BelongsToTenant+LogsActivity; Append-/mutierende Modelle nur `BelongsToTenant`. tenant-scoped exists via `$this->tenantExists(...)`. Media signiert + Owner-Whitelist im `MediaDownloadController`. Kein stilles Schlucken. Matcher-Fallen (leerer Norm → kein Pseudomatch; Konsistenz vor Buchung). Tests: `Model::create`, `app(CurrentTenant::class)->set`, `AccountingDefaults::ensureFor`, `Role::findOrCreate`. ide-helper positional (`-W -R`, kein `-M`). Gates: `php -d memory_limit=1G vendor/bin/phpstan analyse`, `vendor/bin/pint`, `php -d memory_limit=1G vendor/bin/pest`.

Branch: `feat/wawi-stammdaten-import` (steht; am Ende `--no-ff` nach master + push).

---

## Task 1: CSV-Parser + Spalten-Alias

**Files:**
- Create: `app/Domains/Import/Support/SpaltenAlias.php`, `app/Domains/Import/Support/StammdatenParser.php`
- Test: `tests/Feature/Import/StammdatenParserTest.php`

**Contract:**
- `SpaltenAlias::ALIASSE` (const array Zielfeld → Header-Varianten, lowercase): `name` → `['name','bezeichnung','artikel','artikelname','artikelbezeichnung']`; `einheit` → `['einheit','einh','me','mengeneinheit']`; `abteilung` → `['abteilung','bereich']`; `einkaufspreis` → `['einkaufspreis','ek','ek-preis','preis']`; `mindestbestand` → `['mindestbestand','minbestand','meldebestand']`; `bestand` → `['bestand','anfangsbestand','menge','startbestand']`; `einstandspreis` → `['einstandspreis','wert','bewertungspreis']`; `pg_nummer` → `['pg_nummer','pg','hmv','positionsnummer']`; `lieferant` → `['lieferant','kreditor','supplier','händler']`; `charge_nr` → `['charge_nr','charge','los','chargennummer']`; `mhd` → `['mhd','verfall','verfallsdatum','haltbarkeit']`. Statische `erkenne(array $header): array<string,?string>` (Zielfeld → erkannte Original-Spalte oder null; normalisiert Header per `Str::lower(trim(...))`).
- `StammdatenParser::parseCsv(string $inhalt): array{header: string[], zeilen: array<int, array<string,string>>, mapping: array<string,?string>}`: BOM strippen; Delimiter auto (`;` wenn häufiger als `,` in der ersten Zeile, sonst `,`); `str_getcsv` je Zeile; erste Zeile = Header; `zeilen` als assoziative Arrays (Original-Header → Wert); `mapping` aus `SpaltenAlias::erkenne($header)`.

**Tests:** CSV mit `;`-Trennung + BOM, Header `Bezeichnung;Einheit;Anfangsbestand;EK` → `mapping['name']==='Bezeichnung'`, `mapping['bestand']==='Anfangsbestand'`, `mapping['einkaufspreis']==='EK'`, 2 Datenzeilen korrekt assoziativ. Zweiter Test: `,`-Trennung. Dritter: unbekannte Spalte (`mapping['pg_nummer']===null`).

**Steps:** (1) Tests rot. (2) SpaltenAlias+Parser → grün. (3) pint/phpstan/Suite. (4) Commit `feat(import): CSV-Stammdaten-Parser + Spalten-Alias-Erkennung`.

---

## Task 2: Persistenz + Anfangsbestand-Konto + Wareneingang-Gegenkonto

**Files:**
- Create: `app/Domains/Import/Enums/ImportAktion.php` (`Anlegen`/`Mergen`/`Ueberspringen`+label), `app/Domains/Import/Enums/ImportZeileStatus.php` (`Vorgeschlagen`/`Importiert`/`Uebersprungen`+label)
- Migrate: `…_create_import_batches_table.php`, `…_create_import_zeilen_table.php`
- Create: `app/Domains/Import/Models/ImportBatch.php` (BaseModel, HasMedia, Collection `quelle`), `app/Domains/Import/Models/ImportZeile.php` (BaseModel)
- Modify: `app/Http/Controllers/MediaDownloadController.php` (Owner-Whitelist um `ImportBatch`, tenant-scoped — wie Gefahrstoff/LieferscheinAnalyse)
- Modify: `app/Domains/Accounting/Support/AccountingDefaults.php` (`const ANFANGSBESTAND = '9000';` + Seed-Zeile `[self::ANFANGSBESTAND, 'Anfangsbestand (Eröffnungsbilanz)', KontoTyp::Passiv]`)
- Modify: `app/Domains/Accounting/Actions/Wareneingang.php` (Signatur additiv `…, ?string $gegenkonto = null` als letzter Param; in der Buchung statt fix `VERBINDLICHKEITEN` → `AccountingDefaults::konto($gegenkonto ?? AccountingDefaults::VERBINDLICHKEITEN)->id`)
- Test: `tests/Feature/Import/ImportPersistenzTest.php`, Ergänzung in `tests/Feature/Accounting/FifoBewertungTest.php` (Gegenkonto)

**Schemas:**
- `import_batches`: `id`, `tenant_id` FK cascade, `dateiname` string nullable, `anfangsbestand_modus` string default 'ebk', `mapping` json nullable, `status` string default 'offen', `erstellt_von` nullable FK(users) nullOnDelete, timestamps.
- `import_zeilen`: `id`, `tenant_id` FK cascade, `batch_id` FK(import_batches) cascade, `roh` json nullable, `ziel_typ` string, `name` string nullable, `einheit` string nullable, `abteilung` string nullable, `einkaufspreis` decimal(12,2) nullable, `mindestbestand` decimal(12,2) nullable, `bestand` decimal(12,2) nullable, `einstandspreis` decimal(12,4) nullable, `pg_nummer` string nullable, `lieferant_text` string nullable, `charge_nr` string nullable, `mhd` date nullable, `matched_artikel_id` nullable FK(artikel) nullOnDelete, `matched_lieferant_id` nullable FK(lieferanten) nullOnDelete, `kandidaten` json nullable, `aktion` string default 'anlegen', `status` string default 'vorgeschlagen', `ergebnis_artikel_id` nullable FK(artikel) nullOnDelete, `ergebnis_lieferant_id` nullable FK(lieferanten) nullOnDelete, `wareneingang_bewegung_id` nullable FK(lagerbewegungen) nullOnDelete, timestamps.
- Models: casts (`mapping`/`roh`/`kandidaten`=>'array', `mhd`=>'date', `aktion`=>ImportAktion, `status`=>ImportZeileStatus, mengen decimal). `ImportBatch`: `zeilen()` HasMany, `registerMediaCollections()` Collection `quelle`. `ImportZeile`: `batch()`, `artikel()`(matched), `lieferant()`(matched); `offen(): bool` (status Vorgeschlagen).

**Tests:** Batch + Zeilen anlegen, array-Casts round-trip, Enums casten, `offen()`. Wareneingang mit `gegenkonto = AccountingDefaults::ANFANGSBESTAND` → Buchung Warenbestand an 9000 (Saldo 9000 = betrag), FIFO-Schicht wie gehabt; ohne Gegenkonto → unverändert Verbindlichkeiten (bestehende Tests grün). Media-Download 200 eigener / 403 fremder Tenant.

**Steps:** (1) Tests rot. (2) Enums+Migrations+Models+Konto+Wareneingang-Param+Controller-Owner → grün. (3) ide-helper beide Modelle + Artikel? (nur neue) , pint/phpstan/Suite. (4) Commit `feat(import): Import-Persistenz + Anfangsbestand-Konto (9000) + Wareneingang-Gegenkonto`.

---

## Task 3: Matching + Commit (Kern)

**Files:**
- Create: `app/Domains/Import/Services/ImportMatching.php`, `app/Domains/Import/Services/ImportCommit.php`
- Config: `config/import.php` (`'merge_threshold' => 0.85`)
- Test: `tests/Feature/Import/ImportMatchingTest.php`, `tests/Feature/Import/ImportCommitTest.php`

**Contract:**
- `ImportMatching::fuerZeile(ImportZeile $z, int $tenantId): void` (oder gibt Vorschlag zurück, den der Aufrufer speichert): Artikel-Zeile → `ArtikelMatcher::match($z->name, null, $tenantId)`; bester Score ≥ `config('import.merge_threshold')` → `aktion=Mergen`, `matched_artikel_id`=Kandidat, `kandidaten`=top-k; sonst `aktion=Anlegen`. Lieferant-Zeile → `LieferantMatch::finde($z->lieferant_text ?? $z->name, $tenantId)` → Treffer → `Mergen`+`matched_lieferant_id`, sonst `Anlegen`. **Immer** Status `Vorgeschlagen` (kein Auto-Commit).
- `ImportCommit::commit(ImportZeile $z, int $tenantId, ?int $userId): ImportZeile` (DB::transaction), guard `$z->offen()`:
  - `ziel_typ==='lieferant'`: `Anlegen` → `Lieferant::create([...name=$z->name|lieferant_text, ...])`; `Mergen` → bestehenden `matched_lieferant_id`; `Ueberspringen` → skip. `ergebnis_lieferant_id` setzen.
  - `ziel_typ==='artikel'`: `Anlegen` → `Artikel::create([...alle Felder, abteilung default Abteilung::Verwaltung wenn leer/ungültig...])`; `Mergen` → `matched_artikel_id` nehmen, **nur leere Felder ergänzen** (kein Blind-Overwrite); `Ueberspringen` → skip. Ziel-Artikel = `$ergebnisArtikel`.
    - Bei `$z->bestand > 0` (und nicht Ueberspringen): `$gegenkonto = $z->batch->anfangsbestand_modus === 'verbindlichkeit' ? null : AccountingDefaults::ANFANGSBESTAND;` → `app(Wareneingang::class)->handle($ergebnisArtikel, (float)$z->bestand, $z->einstandspreis ?? $z->einkaufspreis, today()->toDateString(), 'Anfangsbestand-Import', $z->charge_nr, $z->mhd?->toDateString(), $lieferantId, $gegenkonto)` → FIFO-Eröffnungsschicht; `wareneingang_bewegung_id` setzen. `$lieferantId` = `ergebnis_lieferant_id` der zugehörigen Lieferant-Zeile (über `lieferant_text` im selben Batch gemappt) oder null.
    - Bei `Mergen`: `app(ArtikelMatcher::class)->merke($z->name, null, $tenantId, $ergebnisArtikel->id)`.
  - Status `Importiert`, `ergebnis_artikel_id` setzen.

**Tests:**
- Matching: Artikel ohne Treffer → `Anlegen`; Artikel mit existierendem Namens-Match (Embedding/Alias via FakeMatcher; `config(['speech.fake'=>true])`) → `Mergen` + Kandidat.
- Commit anlegen: Lieferant + Artikel werden angelegt; Anfangsbestand 0 → keine Buchung.
- Commit Anfangsbestand **EBK**: Artikel mit bestand 50, einstandspreis 2.00, modus 'ebk' → FIFO-Schicht (menge_rest 50), Bestand 50, Buchung Warenbestand an **9000** (Saldo 9000 == 100,00).
- Commit Anfangsbestand **Verbindlichkeit**: modus 'verbindlichkeit' → Buchung an Verbindlichkeiten (1600), nicht 9000.
- Commit mergen: bestehender Artikel, `Mergen` → kein neuer Artikel, leere Felder ergänzt, kein Overwrite gesetzter Felder; `merke` füttert Gedächtnis (zweite Zeile gleichen Namens matcht ihn).
- Re-Import-Dedup: zwei Batches gleicher Artikelname → zweiter wird als `Mergen` vorgeschlagen (über das im ersten Commit gelernte Gedächtnis), nicht doppelt angelegt.

**Steps:** (1) Tests rot. (2) config + ImportMatching + ImportCommit → grün. (3) pint/phpstan/Suite. (4) Commit `feat(import): Matching (Index als Fundament) + Commit (Stammdaten + Anfangsbestand EBK/Verbindlichkeit)`.

---

## Task 4: Livewire-UI (Datenimport, editierbar)

**Files:**
- Create: `app/Livewire/Import/Datenimport.php` + `resources/views/livewire/import/datenimport.blade.php`
- Modify: `routes/web.php` (Route `/datenimport` Name `datenimport`), `resources/views/layouts/app.blade.php` (Nav „Datenimport" Finanzen-Block, Gate admin/buchhaltung)
- Test: `tests/Feature/Import/DatenimportTest.php`

**Contract:** `#[Layout('layouts.app')]`, `use WithFileUploads;`, `use ScopesTenantValidation;`. Properties: `$datei` (Upload), `$ziel_typ` ('artikel'/'lieferant', Default 'artikel'), `$anfangsbestand_modus` ('ebk'/'verbindlichkeit'), aktueller `$batchId`, `$mapping` (editierbar je Zielfeld → Spalte), `$ist` (je ZeileId editierbare Felder/aktion/matched). Gate `darf()` = admin/buchhaltung, `abort_unless` je Aktion.
- `parsen(StammdatenParser $parser, ImportMatching $matching)`: validate `datei` (`file`, `mimes:csv,txt`, max 4096); `ImportBatch` anlegen (Datei als Media `quelle`, modus); CSV parsen; je Datenzeile eine `ImportZeile` (geparste Felder via `mapping`, `ziel_typ`); `ImportMatching::fuerZeile` je Zeile. `$mapping` aus Parser vorbelegen (editierbar).
- `mappingAnwenden()`: parst die Zeilen mit dem (korrigierten) `$mapping` neu (Felder der `ImportZeile` aktualisieren), Matching erneut — damit das Spalten-Mapping editierbar wirkt.
- `bestaetigeZeile(int $zeileId, ImportCommit $commit)`: tenant-scoped Validierung der editierten Felder (`matched_artikel_id` nullable `tenantExists('artikel')`, `matched_lieferant_id` nullable `tenantExists('lieferanten')`, `bestand` nullable numeric min:0, `einstandspreis` nullable numeric min:0); `aktion`/Felder aus `$ist[$zeileId]` auf die Zeile übernehmen; `try { $commit->commit(...) } catch (\InvalidArgumentException $e) { addError(...) }`.
- `bestaetigeAlle(ImportCommit $commit)`: über alle offenen Zeilen des Batch (Lieferanten zuerst), je Zeile commit; Fehler je Zeile sammeln, nicht abbrechen.
- `render()`: aktueller Batch + Zeilen, Artikel-/Lieferantenlisten (tenant-scoped), Status-Zähler.
- View: Upload-Karte (Datei, Ziel-Typ, Anfangsbestand-Modus), **Spalten-Mapping-Block** (je Zielfeld ein Select über die Datei-Header, vorbelegt, „Mapping anwenden"-Button), Zeilen-Tabelle (geparste Werte editierbar | Aktion-Select | Match-Kandidat-Select | bei Artikel Anfangsbestand/Einstandspreis editierbar | „Übernehmen"/„Überspringen"), „Alle übernehmen"-Button, Status-Badges. DSGVO-Hinweis-Kasten.

**Tests:** Gate 403 fremde Rolle; `assertOk` buchhaltung. Upload CSV (`UploadedFile::fake()->createWithContent('artikel.csv', "Bezeichnung;Einheit;Anfangsbestand;EK\nMehl;kg;50;2,00\n")` — Variable halten) → `parsen` legt Batch + 1 Zeile an (`assertSee('Mehl')`). `bestaetigeZeile` → Artikel „Mehl" existiert, Bestand 50, FIFO-Schicht. Fremd-Tenant-`matched_artikel_id` → Validierungsfehler.

**Steps:** (1) Tests rot. (2) Component+View+Route+Nav → grün. (3) pint/phpstan/Suite. (4) Commit `feat(import): Datenimport-UI (editierbares Spalten-Mapping + HITL-Vorschau)`.

---

## Abschluss (nach Task 4)
- DemoSeeder: einen Demo-`ImportBatch` (eine importierte + eine offene Zeile) für sichtbare Daten. `migrate:fresh --seed` grün.
- Screenshot `/datenimport` (shots.mjs Route, MFA-Flow, 2FA reset).
- README-Testzähler, `docs/datenimport.md`, Wiki + Screenshot, Memory.
- Opus-Final-Review (Tenant-Scope, kein Blind-Overwrite beim Merge, kein stilles Schlucken, Matcher-Fallen, Anfangsbestand-Buchung korrekt, Outcome-Anker), Fixes, dann `--no-ff` Merge nach master + push.
