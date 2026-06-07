# WaWi-Normen-Erweiterung — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. TDD je Task, volle Suite als Gate zwischen Tasks.

**Goal:** Die Warenwirtschaft um vier Normbereiche erweitern (#2 Pflegehilfsmittel § 40 SGB XI, #5 Charge/MHD Art. 18 VO 178/2002, #1 Gefahrstoffverzeichnis § 6 GefStoffV, #3 Beschaffung), alle auf der FIFO-Spine.

**Architecture:** `app/Domains/Accounting`. Spine: `Wareneingang`→`Lagerschicht`(Lot, charge_nr/mhd vorhanden)→`Warenverbrauch` FIFO→`Schichtabgang`(append-only). #2 hängt `resident_id` an `Schichtabgang`; #5 hängt `Lieferant`/`lieferant_id` an die Schicht und nutzt #2 für die Vorwärts-Verfolgung; #3 wiederverwendet `Lieferant`.

**Tech Stack:** Laravel 13, Livewire 4, Pest, Larastan L5, Pint. Spec: `docs/superpowers/specs/2026-06-07-wawi-normen-erweiterung-design.md`.

**Konventionen (verbindlich):** BaseModel=BelongsToTenant+LogsActivity; mutierende/Append-Modelle nur `BelongsToTenant` (Vorbild `Lagerschicht`/`Schichtabgang`). Tests: `Model::create`, `app(CurrentTenant::class)->set($t)`, `AccountingDefaults::ensureFor($t->id)`, `Role::findOrCreate('rolle')`. Migrations `database/migrations/2026_06_1X_*`. Nach Modell-Änderung ide-helper positional (`-W -R "FQCN"`, kein `-M`). Kein stilles Schlucken. PHPStan `php -d memory_limit=1G vendor/bin/phpstan analyse`, `vendor/bin/pint`. Volle Suite `php -d memory_limit=1G vendor/bin/pest`.

---

## Task 1 (#2): Pflegehilfsmittel-Versorgung (§ 40 SGB XI)

**Files:**
- Migrate: `…_add_pflegehilfsmittel_to_artikel.php` (Artikel: `pflegehilfsmittel` bool default false, `pg_nummer` string nullable)
- Migrate: `…_add_resident_id_to_schichtabgaenge.php` (`resident_id` nullable FK `residents` nullOnDelete + index)
- Modify: `app/Domains/Accounting/Models/Artikel.php` (fillable+casts+`@property`; `scopePflegehilfsmittel`)
- Modify: `app/Domains/Accounting/Models/Schichtabgang.php` (fillable `resident_id`; `resident()` BelongsTo → `App\Domains\Masterdata\Models\Resident`)
- Modify: `app/Domains/Accounting/Actions/Warenverbrauch.php` (Signatur `handle(Artikel $a, float $menge, string $datum, ?string $notiz = null, ?int $residentId = null)`; `resident_id => $residentId` in `abgaenge()->create`)
- Create: `app/Domains/Accounting/Support/PflegehilfsmittelMonitor.php` — `const PAUSCHALE = 42.00;` (per Research bestätigen); `verbrauchProBewohner(int $tenantId, string $monat /* Y-m */): array` → je Resident `[resident, summe, prozent, ampel]` über `Schichtabgang` mit `resident_id != null` join Artikel `pflegehilfsmittel=true`, Bewegung `whereYear/whereMonth` bzw. `whereDate`-Grenzen (ACHTUNG date-Cast: `whereDate` nutzen, nicht `whereBetween`).
- Create: `app/Livewire/Accounting/Pflegehilfsmittel.php` (Route `pflegehilfsmittel`, Gate `admin/buchhaltung/pflegefachkraft`) + View. **EHRLICHKEITS-PFLICHT:** § 40 Abs. 2 SGB XI gilt nur **ambulant**; vollstationäre Heimbewohner haben KEINEN Pauschalen-Anspruch (Träger trägt via Pflegesatz). Die 42-€-Ampel ist interne Kosten-Referenz, KEIN „Anspruch/Erstattung". Seite trägt deutlich sichtbaren Rechtskontext-Hinweis (ambulant vs. stationär). Niemals „Erstattung" für stationäre Bewohner suggerieren.
- Modify: `app/Livewire/Accounting/Buchhaltung.php` (`verbrauch()`: optionale `beweg_resident` durchreichen; nur relevant wenn Artikel `pflegehilfsmittel`) + View (Bewohner-Select)
- Modify: `routes/web.php` + `resources/views/layouts/app.blade.php` (Nav „Pflegehilfsmittel" im Finanzen-Block)
- Test: `tests/Feature/Accounting/PflegehilfsmittelTest.php`

**Contract/Tests (TDD):**
- Verbrauch mit `residentId` schreibt `resident_id` in alle erzeugten Schichtabgänge; ohne → null (Status quo).
- `PflegehilfsmittelMonitor`: 2 Bewohner, je Verbrauch eines `pflegehilfsmittel`-Artikels in einem Monat → korrekte Summe (menge×einstandspreis), Ampel grün/amber/rot um 42 €; Nicht-Pflegehilfsmittel-Verbrauch zählt NICHT; anderer Monat zählt NICHT (date-Grenze testen — letzter Monatstag!).
- Livewire `Pflegehilfsmittel` rendert für buchhaltung-Rolle (`assertOk`/`assertSee` Bewohnername).
- Bestehende `FifoBewertungTest`/`InventurTest` bleiben grün (Signatur-Erweiterung ist additiv/optional).

**Steps:** (1) Test resident_id-Durchreichung schreiben → rot. (2) Migration+Model+Action → grün. (3) Monitor-Test (inkl. Monatsgrenze) → rot. (4) Monitor → grün. (5) Livewire+View+Route+Nav, Smoke-Test → grün. (6) ide-helper, pint, phpstan, volle Suite. (7) Commit `feat(accounting): bewohnerbezogener Pflegehilfsmittel-Verbrauch + Monats-Pauschale (§ 40 SGB XI)`.

---

## Task 2 (#5): Charge/MHD-Rückverfolgung (Art. 18 VO 178/2002) + Lieferant

**Files:**
- Migrate: `…_create_lieferanten_table.php` (`tenant_id`, `name`, `anschrift` nullable, `kontakt` nullable, `lieferantennr` nullable, timestamps)
- Migrate: `…_add_lieferant_id_to_lagerschichten.php` (`lieferant_id` nullable FK `lieferanten` nullOnDelete)
- Create: `app/Domains/Accounting/Models/Lieferant.php` (BaseModel; `schichten()` HasMany; `bestellungen()` kommt in Task 4)
- Modify: `app/Domains/Accounting/Models/Lagerschicht.php` (`lieferant_id` fillable, `lieferant()` BelongsTo, `@property`)
- Modify: `app/Domains/Accounting/Actions/Wareneingang.php` (Signatur `…, ?int $lieferantId = null` letzter Param; `lieferant_id => $lieferantId` in `schichten()->create`)
- Create: `app/Domains/Accounting/Support/Chargenverfolgung.php` — `verfolge(string $chargeNr, int $tenantId): array` → je Schicht: Schicht-Daten, Lieferant (zurück), Abgänge mit Bewegung(datum/abteilung/notiz)+Resident (vor).
- Create: `app/Domains/Accounting/Support/MhdMonitor.php` — `ablaufend(int $tenantId, int $tageVorlauf = 14): Collection` (offene Schichten mhd<=today+vorlauf, sort mhd; flag abgelaufen wenn mhd<today).
- Create: `app/Livewire/Accounting/Rueckverfolgung.php` (Route `rueckverfolgung`, Gate `admin/buchhaltung/kueche`) + View (MHD-Ablaufliste Ampel + Chargen-Suche → Treffer).
- Modify: `app/Livewire/Accounting/Buchhaltung.php` `wareneingang()` + View: Felder `beweg_charge`, `beweg_mhd`, `beweg_lieferant` erfassen + an Action; Lieferanten-Mini-CRUD (`lieferantAnlegen()`).
- Modify: `routes/web.php` + Nav (Finanzen „Rückverfolgung").
- Test: `tests/Feature/Accounting/RueckverfolgungTest.php`

**Contract/Tests (TDD):**
- Wareneingang mit `chargeNr`/`mhd`/`lieferantId` → Schicht trägt alle drei.
- `Chargenverfolgung::verfolge('L123', …)`: nach Eingang(charge L123, Lieferant X) + Verbrauch(resident Y) → Treffer enthält Lieferant X (zurück) und Resident Y + Abteilung/Datum (vor).
- `MhdMonitor::ablaufend`: Schicht mhd morgen → enthalten; mhd in 60 T → nicht (Default-Vorlauf 14); mhd gestern → enthalten+`abgelaufen=true`; menge_rest=0 → nie enthalten.
- Livewire `Rueckverfolgung` rendert; Chargensuche zeigt Treffer.

**Steps:** analog Task 1 (Test→rot→impl→grün je Einheit), ide-helper/pint/phpstan/Suite, Commit `feat(accounting): Charge/MHD-Rückverfolgung + Lieferant (Art. 18 VO 178/2002)`.

---

## Task 3 (#1): Gefahrstoffverzeichnis (§ 6 GefStoffV)

**Files:**
- Migrate: `…_add_gefahrstoff_to_artikel.php` (`gefahrstoff` bool default false)
- Migrate: `…_create_gefahrstoffe_table.php` (`tenant_id`, `artikel_id` FK unique cascadeOnDelete, `signalwort` string nullable, `h_saetze` json nullable, `p_saetze` json nullable, `ghs_piktogramme` json nullable, `mengenbereich` string nullable, `arbeitsbereiche` text nullable, `lagerort` string nullable, `betriebsanweisung` text nullable, `sdb_version_datum` date nullable, timestamps). Norm: § 6 Abs. 12 Nr. 1–5 GefStoffV (Pflicht: Bezeichnung/Einstufung-CLP/Mengenbereich/Arbeitsbereiche/SDB-Verweis).
- Create: `app/Domains/Accounting/Enums/GhsPiktogramm.php` (GHS01..GHS09 + `label()`)
- Create: `app/Domains/Accounting/Models/Gefahrstoff.php` (BaseModel, `implements HasMedia`, `use InteractsWithMedia`; casts json arrays; `artikel()` BelongsTo; Media-Collection `sdb`)
- Modify: `app/Domains/Accounting/Models/Artikel.php` (`gefahrstoff` fillable/cast bool; `gefahrstoffDaten()` HasOne)
- Create: `app/Livewire/Accounting/Gefahrstoffverzeichnis.php` (Route `gefahrstoffe`, Gate `admin/haustechnik/kueche/buchhaltung`; Anlegen/Bearbeiten inkl. SDB-Upload via `WithFileUploads`) + View (Piktogramme/Signalwort/Mengenbereich/Arbeitsbereich/SDB-Download).
- Modify: `routes/web.php` + Nav (eigener Block oder Finanzen „Gefahrstoffe").
- Test: `tests/Feature/Accounting/GefahrstoffTest.php`

**Contract/Tests (TDD):**
- `Gefahrstoff` mit json-Feldern (h_saetze/ghs_piktogramme) speichert/liest Array.
- Artikel `gefahrstoff=true` erscheint im Verzeichnis, `false` nicht.
- Livewire: Eintrag anlegen (Signalwort/Mengenbereich/Arbeitsbereich) rendert in Liste; SDB-Upload (`UploadedFile::fake()->create('sdb.pdf')`) landet in Media-Collection (ACHTUNG GC-Falle: Variable halten).
- Gate: Nicht-berechtigte Rolle → 403.

**Steps:** analog, ide-helper/pint/phpstan/Suite, Commit `feat(accounting): Gefahrstoffverzeichnis mit GHS/SDB (§ 6 GefStoffV)`.

---

## Task 4 (#3): Beschaffung / Bestellwesen

**Files:**
- Migrate: `…_create_bestellungen_table.php` (`tenant_id`, `lieferant_id` FK, `bestelldatum` date, `status` string, `erstellt_von` nullable FK users nullOnDelete, `notiz` nullable)
- Migrate: `…_create_bestellpositionen_table.php` (`tenant_id`, `bestellung_id` FK cascade, `artikel_id` FK, `menge_bestellt` decimal, `menge_geliefert` decimal default 0, `einzelpreis` decimal nullable)
- Migrate: `…_add_bestellposition_id_to_lagerschichten.php` (`bestellposition_id` nullable FK nullOnDelete)
- Create: `app/Domains/Accounting/Enums/BestellStatus.php` (Entwurf/Bestellt/TeilweiseGeliefert/Geliefert/Storniert + `label()`)
- Create: `app/Domains/Accounting/Models/Bestellung.php` (BaseModel; `lieferant()`, `positionen()` HasMany, `erstellerName`?) + `Bestellposition.php` (BaseModel; `bestellung()`, `artikel()`; `offen()` = menge_bestellt-menge_geliefert>0)
- Modify: `Lagerschicht.php` (`bestellposition_id` fillable) + `Lieferant.php` (`bestellungen()` HasMany)
- Create: `app/Domains/Accounting/Actions/BestellungAnlegen.php`, `BestellungWareneingang.php` (ruft `Wareneingang` inkl. Lieferant aus Bestellung; erhöht menge_geliefert; Status nachführen; Über-Lieferung→Exception), `app/Domains/Accounting/Support/BedarfsVorschlag.php`
- Create: `app/Livewire/Accounting/Beschaffung.php` (Route `beschaffung`, Gate `admin/buchhaltung`) + View
- Modify: `routes/web.php` + Nav (Finanzen „Beschaffung")
- Test: `tests/Feature/Accounting/BeschaffungTest.php`

**Contract/Tests (TDD):**
- `BestellungAnlegen` legt Bestellung + Positionen an, Status `Bestellt`.
- `BestellungWareneingang` (Teil): menge_geliefert steigt, Status `TeilweiseGeliefert`, FIFO-Schicht entsteht mit `lieferant_id` (aus Bestellung) + `bestellposition_id`; Bestand steigt. Volllieferung → `Geliefert`. Über Restmenge → Exception.
- `BedarfsVorschlag::fuer`: Artikel mit `bestand < mindestbestand` erscheint mit Vorschlagsmenge `mindestbestand-bestand`; ohne mindestbestand/ausreichend nicht.
- Livewire: Bestellung anlegen + Wareneingang gegen Position; „Bedarf übernehmen" füllt Positionen.

**Steps:** analog, ide-helper/pint/phpstan/Suite, Commit `feat(accounting): Beschaffung/Bestellwesen mit Wareneingang gegen Bestellung`.

---

## Abschluss (nach allen 4 Tasks)
- DemoSeeder je Feature mit sichtbaren Daten erweitern; `migrate:fresh --seed` grün.
- Screenshots der neuen Seiten (scripts/shots.mjs, MFA via SHOTS-1, danach reset).
- README-Testzähler + Accounting-Zeile, `docs/`-Doku, Wiki nachziehen.
- Final-Review-Subagent (IDOR/Tenant-Scope, kein stilles Schlucken, Skip-Transparenz), Fixes, dann `--no-ff` Merge nach master + push.
