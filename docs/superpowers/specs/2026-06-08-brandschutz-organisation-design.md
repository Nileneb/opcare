# Brandschutz-Organisation (§ 10 ArbSchG / ASR A2.2/A2.3 / DIN 14096) — Design

**Goal:** Die **organisatorischen** Brandschutz-Pflichten operativ machen, die heute fehlen — Brandschutzordnung
(Dokument), wiederkehrende Brandschutzbegehung mit Mängel-Workflow, und Räumungs-/Evakuierungsübung mit
Frist-Ampel. Eigene Domäne `app/Domains/Brandschutz`, Route `/brandschutz`.

## Abgrenzung (KEINE Redundanz — bereits vorhanden)

- **Brandschutz-Technik-Prüfung** (Feuerlöscher, Brandmeldeanlage, RWA): liegt im **Wartungsplan**
  (`Facility\FacilityAsset`, `AssetKategorie::Brandschutz`, Prüffrist-Ampel) — NICHT neu bauen, im Doc verlinken.
- **Brandschutzhelfer-Ausbildung** (je Mitarbeiter:in): liegt in **Arbeitsschutz-Nachweise**
  (`Personnel\NachweisTyp::Brandschutzhelfer`, ASR A2.2, 60-Monats-Intervall) — NICHT neu bauen.

Dieses Modul ergänzt rein die **betrieblich-organisatorische** Ebene.

## Norm-Anker

- **§ 10 ArbSchG „Erste Hilfe und sonstige Notfallmaßnahmen":** Arbeitgeber trifft die für **Brandbekämpfung
  und Evakuierung** erforderlichen Maßnahmen und **benennt** die zuständigen Beschäftigten.
- **ArbStättV Anhang 2.2/2.3 + ASR A2.2 „Maßnahmen gegen Brände"** und **ASR A2.3 „Fluchtwege und Notausgänge":**
  Brandschutzmaßnahmen, Flucht- und Rettungswege, **Räumungsübungen in angemessenen Zeitabständen**.
- **DIN 14096 „Brandschutzordnung"** (Teil A Aushang / Teil B Personen ohne bes. Brandschutzaufgaben /
  Teil C Personen mit bes. Aufgaben) — empfohlene Überprüfung alle 2 Jahre.
- **DGUV Information 205-001 (betrieblicher Brandschutz)** — regelmäßige Eigenkontrolle/Begehung.
- Heime sind **Sonderbauten** (Landesbauordnung) → behördliche Brandverhütungsschau; die hier geführte
  Eigenkontroll-Begehung ist die betriebliche Vorstufe/Nachweisgrundlage.

> **Ehrlich:** opcare führt **Brandschutzordnung (Dokument + Revisions-Ampel), Begehungs-Protokolle mit
> Mängel-Workflow und Räumungsübungs-Nachweise mit Frist-Ampel**. Die behördliche Brandverhütungsschau, die
> bauliche Ertüchtigung und die fachgerechte Wartung der Anlagen bleiben außerhalb (Wartungsplan/Behörde).

## Architektur

Neue Domäne **`app/Domains/Brandschutz`** (kohärenter Bounded Context mit eigenem Norm-Cluster). Vier Modelle
(`BaseModel`), zwei Enums, ein Service. Wiederverwendete Muster: **Dokument-mit-Freigabe** (Hygieneplan-Gold),
**Begehung-mit-Mängel-SSOT**, **Nachweis-mit-Frist** (Latest-Record-Ampel).

### Enums (`app/Domains/Brandschutz/Enums/`)

- **`BrandschutzordnungTeil`** (string-backed): `A`, `B`, `C`. `label()` („Teil A — Aushang" …),
  `zielgruppe()` (A: „Alle (Aushang)", B: „Beschäftigte ohne bes. Brandschutzaufgaben", C: „Personen mit bes.
  Brandschutzaufgaben").
- **`MangelSchwere`** (string-backed): `Gering`, `Wesentlich`, `Kritisch`. `label()`, `ampel()` (Gering→green,
  Wesentlich→amber, Kritisch→red), `rang(): int` (1/2/3 — für „höchste offene Schwere").

### Modelle (`app/Domains/Brandschutz/Models/`)

- **`Brandschutzordnung`** (DIN 14096) — Dokument-mit-Freigabe, gespiegelt von `Hygieneplan`.
  Felder: `tenant_id`, `titel`, `teil` (BrandschutzordnungTeil), `version`, `inhalt` (text nullable),
  `freigegeben_von` (FK users nullable), `freigegeben_am` (date nullable), `revision_intervall_monate`
  (int, default 24), `aktiv` (bool).
  - `freigeber(): BelongsTo<User>`, `naechsteRevision(): ?Carbon` (freigegeben_am + Intervall),
    `status(): string` (entwurf/ueberfaellig/faellig/aktuell — entwurf wenn nie freigegeben),
    `ampel(): string` (entwurf|ueberfaellig→red, faellig→amber, aktuell→green). EXAKT Hygieneplan-Logik.
- **`Brandschutzbegehung`** — ein Begehungs-/Eigenkontroll-Protokoll (DGUV 205-001).
  Felder: `tenant_id`, `bereich` (string, z. B. „Wohnbereich 1", „Küche", „Keller/Technik"),
  `begangen_am` (date), `begangen_von` (FK users nullable), `intervall_monate` (int, default 12),
  `bemerkung` (text nullable).
  - `maengel(): HasMany<Brandschutzmangel>`, `begeher(): BelongsTo<User>`.
  - **Frist-Ampel** (Latest-Record-Muster): `naechsteBegehung(): Carbon` (begangen_am + Intervall),
    `istUeberfaellig(): bool`, `faelligkeitsStatus(): string` ('rot' überfällig / 'gelb' ≤30 Tage / 'gruen').
  - **SSOT Mängel:** `offeneMaengel(): Collection` (maengel mit `behoben_am IS NULL`),
    `hatOffeneMaengel(): bool` delegiert (`isNotEmpty()`), `hoechsteOffeneSchwere(): ?MangelSchwere`.
- **`Brandschutzmangel`** — ein bei einer Begehung festgestellter Mangel.
  Felder: `tenant_id`, `brandschutzbegehung_id`, `beschreibung` (text), `schwere` (MangelSchwere),
  `frist` (date nullable), `behoben_am` (date nullable), `behoben_notiz` (string nullable).
  - `begehung(): BelongsTo`, `istOffen(): bool` (`behoben_am === null`).
- **`Raeumungsuebung`** (Evakuierungsübung, § 10 ArbSchG / ASR A2.3) — Latest-Record-Nachweis.
  Felder: `tenant_id`, `durchgefuehrt_am` (date), `durchgefuehrt_von` (FK users nullable),
  `intervall_monate` (int, default 12), `bereich` (string nullable), `szenario` (string nullable),
  `teilnehmer_anzahl` (int nullable), `dauer_minuten` (int nullable), `erkenntnisse` (text nullable).
  - `leiter(): BelongsTo<User>`, `naechsteUebung(): Carbon` (durchgefuehrt_am + Intervall),
    `istUeberfaellig(): bool`, `faelligkeitsStatus(): string` (rot/gelb/gruen, gelb ≤30 Tage).

### Service (`app/Domains/Brandschutz/Services/`)

- **`BrandschutzMonitor`** (inject `CurrentTenant`) — die EINE Quelle für die Übersichts-Badges:
  - `aktuelleBegehungen(): Collection` — je `bereich` die **jüngste** Begehung (max `begangen_am`).
  - `aktuelleUebung(): ?Raeumungsuebung` — die jüngste Räumungsübung (max `durchgefuehrt_am`).
  - `offeneMaengelAnzahl(): int` — tenant-scoped `Brandschutzmangel`-Count mit `behoben_am IS NULL`.
  - `ueberfaelligeAnzahl(): int` — Summe überfälliger Brandschutzordnungen + jüngster Begehungen je Bereich +
    jüngster Räumungsübung. **Latest-Record-Auswahl per Bereich = inhärente Max-Semantik** (ein nachgetragenes
    älteres Datum wird nicht „jüngste" → setzt die Frist nicht zurück). Frist-Status NUR aus Model-Methoden.

## UI

Livewire **`app/Livewire/Brandschutz/Brandschutz.php`**, Route `/brandschutz` Name `brandschutz`, Gate
`admin`/`haustechnik` (+ superadmin), tenant-scoped (`CurrentTenant`, `ScopesTenantValidation`). Drei Sektionen:
1. **Brandschutzordnung** — anlegen (Titel, Teil A/B/C, Version, Intervall) + Freigabe-Button (setzt
   `freigegeben_am`/`freigegeben_von`) + Revisions-Ampel je Dokument (gespiegelt vom Hygieneplan-Screen).
2. **Begehungen** — Begehung erfassen (Bereich, Datum `before_or_equal:today`, Bemerkung) → Liste der jüngsten
   Begehung je Bereich mit Frist-Ampel + Badge „N offene Mängel" (aus `offeneMaengel()`); je Begehung Mängel
   hinzufügen (Beschreibung, Schwere, Frist) + Mangel als behoben markieren (`behoben_am`, `before_or_equal:today`).
3. **Räumungsübungen** — Übung dokumentieren (Datum `before_or_equal:today`, Bereich, Szenario, Teilnehmerzahl,
   Dauer, Erkenntnisse) → Liste mit Frist-Ampel; jüngste Übung treibt die Header-Frist.
Header-Badge „N überfällig" (BrandschutzMonitor). Norm-Fußnote (§ 10 ArbSchG, ASR A2.2/A2.3, DIN 14096).
Nav: Gruppe „Kalender & Betrieb" (bei Haustechnik/Medizinprodukte/Trinkwasser), Label „Brandschutz", Route in
`$kalenderActive`-`routeIs(...)`-Liste ergänzen.

## Pflicht-Lektionen (Verstoß = Review-Blocker)

- **SSOT:** „offene Mängel" je Begehung aus `offeneMaengel()`/`hatOffeneMaengel()` — keine divergente Blade-Query.
- **Zukunftsdatum:** `begangen_am`, `durchgefuehrt_am`, `behoben_am`, (Freigabe heute) alle `before_or_equal:today`
  — sonst grünt eine zukünftige Begehung/Übung die Ampel fälschlich.
- **Max-Semantik:** „jüngste Begehung/Übung" über `max(datum)` wählen — ein nachgetragener älterer Datensatz darf
  die Frist nicht zurücksetzen.
- **Tenant/IDOR:** jede Schreib-Action lädt das Ziel `where('tenant_id',…)->findOrFail`. Mangel-Aktionen über die
  Begehung→Tenant gesichert. Gate in JEDER Schreibmethode.
- Niemals Errors stummschalten.
- Vorlagen: `app/Domains/Hygiene/Models/Hygieneplan.php` (Dokument-Ampel), `app/Domains/Facility/Models/Trinkwasseranlage.php`
  (Frist-Ampel + offenerBefund-SSOT), `app/Domains/Arbeitsschutz/**` (GBU — SSOT/Monitor/Tenant frisch gebaut),
  `app/Livewire/Facility/Trinkwasser.php` + Blade (UI-Gold), `app/Domains/Facility/Models/FacilityMeldung.php`
  (Mängel/Status), `app/Domains/Personnel/Enums/NachweisTyp.php` (Enum mit ASR-Bezug).

## Tests (Pest, TDD)

- **Enums:** `BrandschutzordnungTeil::zielgruppe` (3), `MangelSchwere::ampel/rang` (3).
- **Brandschutzordnung:** status() entwurf (nie freigegeben), ueberfaellig (freigegeben vor >Intervall),
  faellig (≤30 Tage), aktuell; ampel()-Mapping.
- **Begehung:** Frist-Ampel (frisch grün / vor >Intervall rot / ≤30 Tage gelb); SSOT `offeneMaengel`
  über mehrere Mängel + nach `behoben_am` → leer; `hoechsteOffeneSchwere` (Kritisch schlägt Gering).
- **Räumungsübung:** Frist-Ampel; jüngste Übung treibt Status.
- **`BrandschutzMonitor`:** tenant-scoped (fremder Tenant unsichtbar); `aktuelleBegehungen` = jüngste je Bereich
  (Max-Semantik: nachgetragene ältere Begehung ändert „jüngste" nicht); `offeneMaengelAnzahl`; `ueberfaelligeAnzahl`.
- **UI/Livewire:** Ordnung anlegen+freigeben → Ampel grün; Begehung+Mangel anlegen → offene-Mängel-Badge; Mangel
  behoben → Badge sinkt; Übung dokumentieren → Frist grün; **Gate-403**; **IDOR** (fremd-Tenant); **Zukunftsdatum**
  bei Begehung/Übung/Behebung → Validierungsfehler.
- **Volle Suite** `php -d memory_limit=1G vendor/bin/pest` grün, **Larastan L5** clean, **Pint** clean.

## Risiken & Trade-offs

- **Latest-Record statt Plan/Child:** Begehung/Übung tragen ihr eigenes Intervall; die jüngste je Bereich treibt
  die Ampel (kein separates Plan-Modell). Einfacher, deckt die Pflicht ab; bewusst gegen ein Über-Engineering
  mit Soll-Plan-Matrix entschieden (YAGNI).
- **Eigenkontrolle ≠ behördliche Brandschau:** klar als betriebliche Eigenkontrolle benannt; ersetzt nicht die
  Brandverhütungsschau der Bauaufsicht/Feuerwehr.
- **Bereich als Freitext:** keine feste Bereichs-Taxonomie (Heime variieren); Konsistenz über Eingabe, nicht über
  Enum — bewusst, um nicht an einer starren Liste vorbei zu dokumentieren.
