# Belastungs-Live-Index (§ 5 Abs. 3 Nr. 6 ArbSchG, live) — Design

**Goal:** Den GBU-Faktor „psychische Belastung" **datengetrieben und live** machen: aus vorhandenen Signalen
(Pflegelast, Personaldeckung, Ergonomie/Spitzenzeit) einen **Belastungsindex je Wohnbereich** berechnen; bei
Schwellen-Überschreitung **Meldung an die Leitung** + per Klick eine **Entlastungsmaßnahme** als `Schutzmassnahme`
an der GBU anlegen. **Mode A (vom User gewählt): schicht-/wohnbereichsbezogen, KEIN Personen-Scoring** → keine
Leistungs-/Verhaltenskontrolle, keine § 87 BetrVG-Mitbestimmungsfalle.

## Norm-Anker

- **§ 5 Abs. 1 + Abs. 3 Nr. 6 ArbSchG** — Beurteilung der Arbeitsbedingungen inkl. **psychischer Belastung**.
  Dieses Modul macht die statische GBU-Einschätzung zu einem **laufenden** Abbild der realen Arbeitsbedingungen.
- **§ 3 Abs. 1 / § 4 ArbSchG** — Maßnahmen treffen + anpassen (TOP) → der „Entlasten"-Klick erzeugt eine
  dokumentierte `Schutzmassnahme`.
- **§ 618 BGB / Fürsorgepflicht** — Arbeitgeber schützt vor Überlastung.
- **Abgrenzung (bewusst):** kein Einzel-Score, kein Ranking, **kein `LogsActivity` auf Personen** — wie beim
  [[opcare-bundesland-buchung-energie|Energiebarometer]]. Personen erscheinen nur als „Besetzung der betroffenen
  Schicht". Damit bleibt es § 5-ArbSchG-Arbeitsbedingungs-Bewertung, NICHT § 87 BetrVG-Personenkontrolle.

## Ehrliche Granularität (kein Overclaiming)

- **Pflegelast je Wohnbereich = echt:** aus `CarePlanning\RiskItem` (RiskType-gewichtet) über
  SIS-Assessment → Bewohner → `Room.station` aggregiert je Station, plus Pflegegrad-Mix der belegten Zimmer.
- **Personaldeckung = mandantenweit:** `Betreuungsschluessel`/§ 113c (`StaffingAnalysis::deckungGesamt/Fachkraft`)
  und `SpitzenzeitAnalyzer`/`ScheduleQualityAnalyzer` rechnen heute tenant-weit (Personal ist nicht an Stationen
  gebunden). Diese fließen als **gemeinsamer mandantenweiter Druckfaktor** in jeden Wohnbereich ein — ehrlich so
  benannt. Erweiterbar zu echter Wohnbereichs-Deckung, sobald Stations-Dienstpläne existieren.

## Architektur

Erweiterung der Domäne **`app/Domains/Arbeitsschutz`** (Heimat des „live § 5 ArbSchG"); liest Signale aus
`Scheduling`/`CarePlanning`. Eine Enum, ein Config-Modell, ein Analyzer + DTO, ein Meldungs-Modell, zwei Services,
eine Notification. UI im Dienstplan (Panel) + auf dem GBU-Screen (Meldungs-Workflow).

### Enum (`app/Domains/Arbeitsschutz/Enums/`)

- **`Belastungsstufe`** (string): `Gering`, `Erhoeht`, `Hoch`, `Kritisch`. `label()`, `ampel()`
  (Gering→green, Erhoeht→green, Hoch→amber, Kritisch→red), `rang(): int` (1–4), `istMeldepflichtig(): bool`
  (Hoch+Kritisch ≥ Schwelle).

### Config (`app/Domains/Arbeitsschutz/Models/BelastungsKonfig.php`, BaseModel, eine Zeile je Tenant)

Gewichte + Schwellen, editierbar (Muster `StaffingConfig`). **WICHTIG `protected $attributes`-Defaults**
(sonst null×Gewicht=0 bei `firstOrCreate` — bekannte Falle). Felder (alle mit Default):
`gewicht_pflegelast` (Default 40), `gewicht_deckung` (35), `gewicht_spitzenzeit` (15), `gewicht_ergonomie` (10),
`schwelle_hoch` (60), `schwelle_kritisch` (80). `BelastungsKonfig::ensureFor(int $tenantId): self` (firstOrCreate).

### Analyzer + DTO (`app/Domains/Arbeitsschutz/Services/BelastungsAnalyzer.php`, `…/Data/BelastungsBefund.php`)

- **`BelastungsBefund`** (spatie Data): `stationId` (int|null), `wohnbereich` (string), `stufe` (Belastungsstufe),
  `score` (int 0–100), `signale` (array<string,string> — Label→Wert, z. B. „Pflegelast"→„hoch (4 Risiken)",
  „Personaldeckung"→„78 %").
- **`BelastungsAnalyzer::analysiere(int $tenantId, StaffingAnalysis $staffing, array $qualityFindings, ?SpitzenzeitAnalyse $spitzen = null): Collection<BelastungsBefund>`**
  — je belegter Station ein Befund:
  - Pflegelast-Score (0–100): gewichtete Summe der `RiskItem` (RiskType-Gewicht) der Bewohner der Station,
    normiert auf Bewohnerzahl + Pflegegrad-Mix.
  - Deckungs-Score (mandantenweit): aus `100 - deckungGesamt()` (Unterdeckung), Fachkraft-Unterdeckung stärker.
  - Spitzenzeit-/Ergonomie-Score: aus Anzahl/Schwere der Findings.
  - Gesamt = gewichtete Summe (BelastungsKonfig) → `Belastungsstufe` via Schwellen. **Frei von Personenbezug.**

### Modell (`app/Domains/Arbeitsschutz/Models/Belastungsmeldung.php`, BaseModel)

Persistierte Schwellen-Überschreitung (Audit + Leitungs-Workflow, § 6 ArbSchG-Doku). Felder:
`tenant_id`, `station_id` (nullable, FK stations nullOnDelete), `wohnbereich` (string), `stufe` (Belastungsstufe),
`score` (int), `signale` (json/array), `gemeldet_am` (date), `quittiert_von` (FK users nullable),
`quittiert_am` (date nullable), `schutzmassnahme_id` (FK arbeitsschutz `schutzmassnahmen` nullable — die
verknüpfte Entlastungsmaßnahme), `notiz` (string nullable).
- `istOffen(): bool` (`quittiert_am === null`), `quittierer()`/`schutzmassnahme()` BelongsTo, `station()` BelongsTo.

### Services

- **`BelastungMelden`** (inject CurrentTenant): `handle(BelastungsBefund $befund): ?Belastungsmeldung` — legt eine
  `Belastungsmeldung` an, **Dedupe: nur EINE offene Meldung je `station_id` pro Tag/Woche** (kein Spam), und
  versendet `BelastungKritisch`-Notification an alle `admin`/`super-admin` des Tenants. Nur bei
  `stufe->istMeldepflichtig()`. Gibt null zurück wenn schon offene Meldung existiert (kein Fehler).
- **`EntlastungErgreifen`** (inject CurrentTenant): `handle(Belastungsmeldung $meldung, Gefaehrdungsbeurteilung $gbu, string $beschreibung, ?string $frist): Schutzmassnahme`
  — findet/erstellt an der `$gbu` die `Gefaehrdung` mit `faktor = PsychischeBelastung` (find-or-create),
  legt darauf eine `Schutzmassnahme` (typ `Organisatorisch`) an, setzt `meldung.schutzmassnahme_id`.
  Schließt den Kreis Signal → dokumentierte Maßnahme. Tenant-scoped, in DB::transaction.

### Notification

- **`BelastungKritisch`** (database + broadcast, Muster `VertretungBenoetigt`): „Wohnbereich {wohnbereich}:
  Belastung {stufe} ({score}/100) — {Top-Signal}." Verlinkt auf den Dienstplan/Belastungs-Panel.

## UI

1. **Belastungs-Panel im Dienstplan** (`app/Livewire/Scheduling/Dienstplan.php` render + Blade, neben dem
   Betreuungsschlüssel-Panel): je Wohnbereich Belastungs-Ampel (`BelastungsAnalyzer` mit dem bereits berechneten
   `$staffing`/`$qualityFindings`) + Signal-Aufschlüsselung; bei meldepflichtiger Stufe **„Leitung melden"**-Button
   (→ `BelastungMelden`) und **„Entlasten"**-Button (öffnet GBU-Maßnahmen-Dialog: GBU/Arbeitsbereich wählen,
   Beschreibung vorbefüllt → `EntlastungErgreifen`). Gate wie Dienstplan (admin/pflegefachkraft).
2. **Offene Belastungsmeldungen + Quittierung** auf dem GBU-Screen
   (`app/Livewire/Arbeitsschutz/Gefaehrdungsbeurteilung.php`): Sektion „Belastungsmeldungen" — offene Meldungen
   (SSOT: `Belastungsmeldung` where `quittiert_am IS NULL`), je Meldung Stufe/Signale + **Quittieren** +
   Verweis auf die verknüpfte Entlastungsmaßnahme. So hat der Leitungs-Workflow (offen→quittiert) eine Heimat.
3. **Config-Editor** (Gewichte/Schwellen) auf der Arbeitsrecht-Seite (`app/Livewire/Scheduling/Arbeitsrecht.php`),
   Muster der bestehenden Regel-Editoren.

## Kritische Dateien

- `app/Domains/Arbeitsschutz/{Enums/Belastungsstufe, Models/BelastungsKonfig, Models/Belastungsmeldung,
  Services/BelastungsAnalyzer, Services/BelastungMelden, Services/EntlastungErgreifen, Data/BelastungsBefund,
  Notifications/BelastungKritisch}.php` — neu.
- `database/migrations/…_create_belastungs_konfigs_table.php`, `…_create_belastungsmeldungen_table.php`.
- `app/Livewire/Scheduling/Dienstplan.php` + Blade — Belastungs-Panel.
- `app/Livewire/Arbeitsschutz/Gefaehrdungsbeurteilung.php` + Blade — Meldungs-Sektion.
- `app/Livewire/Scheduling/Arbeitsrecht.php` + Blade — Config-Editor.
- `app/Domains/Identity/Database/Seeders/DemoSeeder.php` — eine kritische Demo-Belastung (Wohnbereich mit vielen
  RiskItems + Unterdeckung) + eine offene Meldung.

## Pflicht-Lektionen (Verstoß = Review-Blocker)

- **SSOT:** offene Meldungen aus EINER Quelle (`Belastungsmeldung where quittiert_am null`); bool + View speisen
  sich daraus.
- **Kein Personenbezug:** Analyzer/Meldung dürfen KEINEN `user_id`-Score führen, kein Ranking, kein LogsActivity
  auf Personen. (Belastungsmeldung ist BaseModel mit LogsActivity — das loggt die MELDUNG/Station, nicht Personen;
  ok. Falls Bedenken: nur `extends Model + BelongsToTenant`. Entscheidung: BaseModel ok, da kein user-Score-Feld.)
- **StaffingConfig-Falle:** `BelastungsKonfig` braucht `protected $attributes`-Defaults, sonst null×Gewicht=0.
- **Zukunftsdatum:** Maßnahmen-Frist über die GBU-Validierung (`before_or_equal` gilt für umgesetzt/geprüft, NICHT
  für `frist` — Frist ist Zukunft). `gemeldet_am`/`quittiert_am` = serverseitig today().
- **Tenant/IDOR:** alle Schreib-Actions tenant-scoped findOrFail; Gate in jeder Methode. Niemals Errors
  stummschalten.
- **Dedupe:** keine doppelten Meldungen je Station/Zeitraum.

## Tests (Pest, TDD)

- **Belastungsstufe:** ampel/rang/istMeldepflichtig.
- **BelastungsKonfig:** ensureFor legt mit Defaults an (firstOrCreate, keine 0-Gewichte).
- **BelastungsAnalyzer:** Station mit vielen schweren RiskItems + niedriger Deckung → Stufe Kritisch; ruhige
  Station + gute Deckung → Gering; Score 0–100 begrenzt; **kein user_id im Befund**; Signale befüllt.
- **BelastungMelden:** legt Meldung an + Notification an Admin; Dedupe (zweiter Aufruf gleiche Station → null,
  keine zweite Meldung); nur bei meldepflichtiger Stufe.
- **EntlastungErgreifen:** find-or-create psychische-Belastung-Gefaehrdung an der GBU + Schutzmassnahme +
  `meldung.schutzmassnahme_id` gesetzt; tenant-scoped.
- **UI:** Dienstplan zeigt Belastungs-Panel; „melden" erzeugt Meldung; „entlasten" erzeugt GBU-Maßnahme;
  GBU-Screen listet offene Meldung + quittieren entfernt sie aus „offen"; Gate-403; IDOR.
- **Volle Suite** grün, **Larastan L5** clean, **Pint** clean.

## Risiken & Trade-offs

- **Gewichtung/Schwellen sind Heuristik:** bewusst editierbar (BelastungsKonfig) statt hartkodiert; Defaults sind
  fachlich plausibel, keine Scheingenauigkeit.
- **Mandantenweite Deckung:** ehrlich benannt; nicht als per-Wohnbereich-Präzision verkauft. Erweiterbar.
- **Mode C (individuelle Auto-Meldung) bewusst NICHT gebaut** (§ 87 BetrVG/DSFA) — Doku verweist darauf als
  optionale spätere Stufe hinter Betriebsvereinbarungs-Schalter.
