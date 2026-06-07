# WaWi-Stammdaten-Datenimport (Mandanten-Onboarding) — Design

**Datum:** 2026-06-07
**Status:** Design — vom User approved (2026-06-07).

## Programm-Kontext

Teil des KI-Warenwirtschafts-Programms. Erhebt den in der Lieferschein-Capture
([[opcare-lieferschein-capture]]) gebauten **Artikel-Matching-Index** zum geteilten **WaWi-Fundament**: der
Datenimport ist sein zweiter Consumer (Dedup/Merge beim Import) und macht ihn dabei besser (bestätigte Merges
füttern das Match-Gedächtnis). opcare ist System of Record für Tenant + Katalog + Import; **prefilter bleibt
unangetastet** — nur das Upload→Parse→`COLUMN_ALIASES`-Muster wird geborgt (opcare-nativ, tenant-scoped,
DSGVO-lokal).

## Ziel

Ein:e Admin lädt eine Datei (Artikel-/Lieferanten-/Anfangsbestand-Liste) hoch; das System parst sie, gleicht jede
Zeile gegen den bestehenden Katalog ab (anlegen vs. mergen) und legt nach **menschlicher Bestätigung** Lieferanten,
Artikel und einen **bewerteten Anfangsbestand** (FIFO-Schicht + Buchung) an. Danach ist ein Mandant einsatzbereit.

**Leitprinzip (User-Wunsch):** **nicht starr, sondern editierbar** — Auto-Spaltenerkennung mit manueller
Mapping-Korrektur, zeilenweise Aktions-/Kandidaten-Korrektur, umschaltbarer Anfangsbestand-Buchungsmodus. Kein
stiller Überschreib.

**Outcome-Anker:** Nach Commit hat der Mandant Katalog + Lieferanten + bewerteten Anfangsbestand — direkt sichtbar
in Buchhaltung/Inventur. Re-Import derselben Datei matcht Bestehendes → Merge/Skip statt Dublette.

## Architektur-Leitplanken (verbindlich)

- Neuer Bounded Context `app/Domains/Import` (hängt an Accounting: Artikel/Lieferant/Wareneingang/AccountingDefaults;
  an Capture: `ArtikelMatcher`/`LieferantMatch`/`TextNorm`).
- `BaseModel` = BelongsToTenant + LogsActivity; Append-/mutierende Modelle nur `BelongsToTenant`.
- Livewire 4: `#[Layout('layouts.app')]`, `abort_unless`-Gate je Schreibaktion, `WithFileUploads`,
  `ScopesTenantValidation`/`$this->tenantExists(...)` (kein nacktes `exists:`), Media-Download signiert +
  Owner-Whitelist ([[opcare-media-download-owner-pattern]]).
- **Kein stilles Schlucken**; Matcher-Fallen beachten ([[opcare-lieferschein-capture]]: leerer Norm, Konsistenz).
- Tests Pest, `Model::create`, `app(CurrentTenant::class)->set`, `Role::findOrCreate`. PHPStan L5
  (`php -d memory_limit=1G vendor/bin/phpstan analyse`), Pint. ide-helper positional (`-W -R`, kein `-M`).

## Komponenten

### Parser

- `app/Domains/Import/Support/StammdatenParser.php`: CSV via PHP-nativem `fgetcsv` (dependency-frei; Trennzeichen
  auto-detect `;`/`,`, BOM strippen, UTF-8). Header-Zeile → Spalten-Map über `COLUMN_ALIASES`
  (`app/Domains/Import/Support/SpaltenAlias.php`): erkennt je Zielfeld mehrere Header-Varianten
  (`name|bezeichnung|artikel|artikelname`, `einheit|einh|me`, `abteilung`, `einkaufspreis|ek|preis`,
  `mindestbestand|minbestand|meldebestand`, `bestand|anfangsbestand|menge`, `einstandspreis|ek-preis|wert`,
  `pg_nummer|pg|hmv`, `lieferant|kreditor|supplier`, `charge|los`, `mhd|verfall`). Rückgabe: `array{header: string[],
  zeilen: array<int, array<string,string>>, mapping: array<string,?string>}` (Zielfeld → erkannte Spalte; null =
  nicht erkannt, manuell zuzuordnen). **XLSX:** Folge-Schritt (eigene Lib, z. B. openspout, später nachziehen) —
  jetzt explizit out of scope, im Parser als „nur CSV unterstützt"-Hinweis behandelt (kein stiller Fehlversuch).

### Persistenz (HITL, spiegelt das Capture-Muster)

- Enum `app/Domains/Import/Enums/ImportAktion.php` (`Anlegen`/`Mergen`/`Ueberspringen` + label()).
- Enum `app/Domains/Import/Enums/ImportZeileStatus.php` (`Vorgeschlagen`/`Importiert`/`Uebersprungen` + label()).
- `app/Domains/Import/Models/ImportBatch.php` (BaseModel, `implements HasMedia`, Collection `quelle`): `dateiname`,
  `anfangsbestand_modus` (string `ebk`/`verbindlichkeit`), `mapping` (json — bestätigte Spalten-Map), `status`
  (string `offen`/`abgeschlossen`), `erstellt_von`, Zähler-Accessors. `zeilen()` HasMany.
- `app/Domains/Import/Models/ImportZeile.php` (BaseModel): `batch_id`, `roh` (json — Originalzeile), `ziel_typ`
  (string `artikel`/`lieferant`), geparste Felder (`name`, `einheit`, `abteilung`, `einkaufspreis`,
  `mindestbestand`, `bestand`, `einstandspreis`, `pg_nummer`, `lieferant_text`, `charge_nr`, `mhd`),
  `matched_artikel_id`/`matched_lieferant_id` (nullable FK nullOnDelete), `kandidaten` (json), `aktion`
  (ImportAktion), `status` (ImportZeileStatus), `ergebnis_artikel_id`/`ergebnis_lieferant_id` (nullable, nach
  Commit), `wareneingang_bewegung_id` (nullable). `offen(): bool`.

### Matching (Index als Fundament)

`ImportMatching`-Service (`app/Domains/Import/Services/ImportMatching.php`): je geparster Artikel-Zeile
`ArtikelMatcher::match(name, lieferantId, tenantId)` → bester Treffer ≥ Schwelle → Vorschlag `Mergen` (+Kandidat),
sonst `Anlegen`. Je Lieferant-Zeile `LieferantMatch::finde` → `Mergen`/`Anlegen`. Schwellen konfigurierbar
(`config('import.merge_threshold')`, Default 0.85), aber **immer HITL-bestätigt** (kein Auto-Merge ohne Mensch).

### Anfangsbestand-Buchung (wahlweise echte Verbindlichkeit)

- Neues Konto in `AccountingDefaults`: `ANFANGSBESTAND = '9000'`, Name „Anfangsbestand (Eröffnungsbilanz)",
  `KontoTyp::Passiv`. Idempotent geseedet.
- `Wareneingang::handle` **additiv** um optionales `?string $gegenkonto = null` (Default → `VERBINDLICHKEITEN`,
  unverändert für alle bestehenden Aufrufer). Bei gesetztem Gegenkonto bucht der Eingang *Warenbestand an
  <gegenkonto>*.
- Commit-Modus je Batch (`anfangsbestand_modus`): `ebk` (Default) → `gegenkonto = ANFANGSBESTAND`; `verbindlichkeit`
  → `gegenkonto = null` (= Verbindlichkeiten, echte offene Lieferung). In beiden Fällen entsteht die FIFO-Schicht.

### Commit

`ImportCommit`-Service (`app/Domains/Import/Services/ImportCommit.php`), je bestätigter `ImportZeile`
(DB::transaction):
- **Lieferant**: `Anlegen` → `Lieferant::create`; `Mergen` → bestehenden nehmen (kein Überschreib der Stammdaten
  ohne explizite Korrektur); `Ueberspringen` → skip. `ergebnis_lieferant_id` setzen.
- **Artikel**: `Anlegen` → `Artikel::create` (alle geparsten Felder); `Mergen` → `matched_artikel_id` nehmen (Felder
  nur ergänzen wo leer, kein Blind-Overwrite); `Ueberspringen` → skip. Bei Neuanlage/Merge + `bestand > 0` →
  `Wareneingang::handle($artikel, $bestand, $einstandspreis ?? $einkaufspreis, today, 'Anfangsbestand-Import',
  $chargeNr, $mhd, $lieferantId, $gegenkonto)` → FIFO-Eröffnungsschicht. `merke()` füttert das Match-Gedächtnis bei
  bestätigtem Merge. Status `Importiert`, `ergebnis_artikel_id`/`wareneingang_bewegung_id` setzen.
- Reihenfolge: Lieferanten vor Artikeln (Artikel referenzieren Lieferant).

### UI

Livewire `app/Livewire/Import/Datenimport.php` (Route `/datenimport`, Nav „Datenimport" Finanzen-Block, Gate
admin/buchhaltung): Upload → `parsen()` (Parser + Matching, persistiert Batch+Zeilen) → **Vorschau**: Spalten-Map
mit manueller Korrektur (Auto-Erkennung editierbar), Zeilen-Tabelle (geparste Werte | Ziel-Typ | Aktion-Select
anlegen/mergen/überspringen | Match-Kandidat-Select | bei Artikel Anfangsbestand/Einstandspreis editierbar),
Anfangsbestand-Modus-Schalter (EBK/Verbindlichkeit). `bestaetigeZeile(id)` / `bestaetigeAlle()` → `ImportCommit`.
Fehler je Zeile inline (kein Crash). DSGVO-Hinweis: „Nur Waren-/Lieferantendaten — keine Bewohnerdaten."

## DSGVO

Nur Artikel/Lieferant/Bestand — **null Personendaten**. Upload-Datei tenant-scoped + signiert (Media-Owner-Whitelist
um `ImportBatch` erweitern). UI warnt vor personenbezogenen Inhalten.

## Verifikation

- `StammdatenParser`-Unit (CSV `;`/`,`, BOM, Header-Alias-Erkennung, fehlende Spalte → null im Mapping).
- `ImportMatching` (anlegen ohne Treffer, mergen mit starkem Treffer, Schwelle).
- `ImportCommit` Feature: Lieferant+Artikel anlegen; Artikel mergen (kein Blind-Overwrite); Anfangsbestand EBK
  (Buchung Warenbestand an 9000, FIFO-Schicht, Bestand steigt) und Verbindlichkeit-Modus (an Verbindlichkeiten);
  Merge füttert Match-Gedächtnis; Re-Import → Dublette wird als Merge/Skip vorgeschlagen, nicht doppelt angelegt.
- Livewire-Smoke (Rolle buchhaltung), Gate-403, tenant-scoped (Fremd-Tenant-Match abgewiesen), Spalten-Mapping-
  Korrektur wirkt. Media-Download 200/403.
- Volle Suite/PHPStan/Pint. `migrate:fresh --seed` + Demo-Import. Screenshot + Doku/Wiki/Memory.
