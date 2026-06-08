# Gefährdungsbeurteilung (§ 5 / § 6 ArbSchG) — Design

**Goal:** Den GBU-Prozess (Gefährdungsbeurteilung) als eigenständiges Arbeitsschutz-Modul scharf machen —
das gesetzliche Fundament, das ermittelt, *welche* Arbeitsschutzmaßnahmen erforderlich sind. Bisher deckt
opcare nur die Folge-Nachweise ab (`Personnel\Arbeitsschutz`: Unterweisung, arbeitsmed. Vorsorge, Erste Hilfe,
BEM, ASiG/DGUV-V2), nicht aber die GBU, die diese überhaupt erst begründet.

## Norm-Anker

- **§ 5 ArbSchG „Beurteilung der Arbeitsbedingungen"** (Pflicht jedes Arbeitgebers):
  - Abs. 1: durch Beurteilung der Gefährdung ermitteln, welche Maßnahmen erforderlich sind.
  - Abs. 2: je nach Art der Tätigkeit; bei gleichartigen Bedingungen reicht ein Arbeitsplatz/eine Tätigkeit.
  - Abs. 3: **6 Gefährdungsfaktoren** — (1) Arbeitsstätte/Arbeitsplatz-Gestaltung, (2) physikalische/chemische/
    biologische Einwirkungen, (3) Arbeitsmittel/Maschinen/Geräte/Anlagen/Arbeitsstoffe, (4) Arbeits-/
    Fertigungsverfahren, Arbeitsabläufe, Arbeitszeit, (5) unzureichende Qualifikation/Unterweisung,
    **(6) psychische Belastungen** (seit 2013 ausdrücklich Pflicht, am häufigsten geprüft).
- **§ 6 ArbSchG „Dokumentation":** Unterlagen, aus denen **Ergebnis der GBU, festgelegte Maßnahmen und
  Ergebnis ihrer Überprüfung** ersichtlich sind. Bei gleichartiger Gefährdung zusammengefasste Angaben.
- **§ 3 Abs. 1 ArbSchG:** Maßnahmen auf Wirksamkeit prüfen und **erforderlichenfalls anpassen** (Fortschreibung).
- **§ 4 ArbSchG (Maßnahmen-Grundsätze):** Rangfolge **TOP** — Technisch vor Organisatorisch vor Personenbezogen.

> **Ehrlich:** opcare führt **GBU-Register + Gefährdungen + Maßnahmen (TOP) + Frist-Ampel zur Fortschreibung +
> Wirksamkeitskontrolle + Doku-Export-Grundlage**. Die fachliche Beurteilung (welche Gefährdung, welches Risiko,
> welche Maßnahme) bleibt Aufgabe der verantwortlichen Person/Fachkraft für Arbeitssicherheit.

## Architektur

Neue Domäne **`app/Domains/Arbeitsschutz`** (eigener Bounded Context: arbeitsplatz-/tätigkeitszentriert,
nicht personenzentriert wie `Personnel`). Drei Modelle (alle `BaseModel` = BelongsToTenant + LogsActivity),
drei Enums, zwei Services. Frist-Ampel + Max-Semantik + SSOT-Status gespiegelt vom Reinigungsplan-/Trinkwasser-Muster.

### Enums (`app/Domains/Arbeitsschutz/Enums/`)

- **`Gefaehrdungsfaktor`** (string-backed) — die 6 Kategorien aus § 5 Abs. 3:
  `Arbeitsstaette`, `Einwirkungen`, `Arbeitsmittel`, `Verfahren`, `Qualifikation`, `PsychischeBelastung`.
  `label(): string` (volle Bezeichnung), `nummer(): int` (1–6), `paragraph(): string` → „§ 5 Abs. 3 Nr. N ArbSchG".
- **`Massnahmentyp`** (TOP-Hierarchie, § 4): `Technisch`, `Organisatorisch`, `Personenbezogen`.
  `label(): string`, `rang(): int` (Technisch=1, Organisatorisch=2, Personenbezogen=3 — kleiner = vorrangig).
- **`GbuStatus`** (string-backed): `Entwurf`, `Freigegeben`, `Ueberarbeitung`. `label(): string`.

### Modelle (`app/Domains/Arbeitsschutz/Models/`)

- **`Gefaehrdungsbeurteilung`** — eine GBU je Arbeitsbereich/Tätigkeit.
  Felder: `tenant_id`, `arbeitsbereich` (string, z. B. „Pflege Wohnbereich 1", „Küche", „Haustechnik"),
  `taetigkeit` (string nullable, konkreter), `beschreibung` (text nullable), `erstellt_am` (date),
  `ueberpruefungsintervall_monate` (int, default 12), `letzte_ueberpruefung_am` (date nullable),
  `verantwortlich` (string nullable), `freigegeben_am` (date nullable), `status` (GbuStatus, default Entwurf).
  - `gefaehrdungen(): HasMany<Gefaehrdung>`.
  - **Frist-Ampel** (Fortschreibungs-Frist, gespiegelt von Trinkwasseranlage):
    `naechsteUeberpruefung(): ?Carbon` = (`letzte_ueberpruefung_am` ?? `erstellt_am`) + Intervall;
    `istUeberfaellig(): bool`; `faelligkeitsStatus(): string` ('rot' überfällig / 'gelb' ≤30 Tage / 'gruen').
    Status nur scharf, wenn `status === Freigegeben` (Entwürfe haben keine Fortschreibungs-Frist → 'gruen').
  - **SSOT für offene Maßnahmen** (Lektion!): `offeneMassnahmen(): Collection` = alle `Schutzmassnahme`
    über alle Gefährdungen dieser GBU mit `umgesetzt_am IS NULL`; `hatOffeneMassnahmen(): bool` delegiert
    (`isNotEmpty()`). View/Badge lesen **ausschließlich** über diese Collection.
  - `hoechsteRisikostufe(): ?string` für Anzeige (max über `gefaehrdungen->risikostufe()`).
- **`Gefaehrdung`** — eine identifizierte Gefährdung innerhalb einer GBU.
  Felder: `tenant_id`, `gefaehrdungsbeurteilung_id`, `faktor` (Gefaehrdungsfaktor),
  `beschreibung` (text, z. B. „Heben/Umlagern von Bewohnern → muskuloskelettale Belastung"),
  `wahrscheinlichkeit` (int 1–3), `schwere` (int 1–3).
  - `massnahmen(): HasMany<Schutzmassnahme>`.
  - **Risiko (Nohl-light):** `risikowert(): int` = `wahrscheinlichkeit * schwere` (1–9);
    `risikostufe(): string` → 'gering' (≤2), 'mittel' (3–4), 'hoch' (≥6). (Lücke 5 fällt in 'mittel'… nein:
    Schwelle exakt: ≤2 gering, 3–4 mittel, ≥6 hoch; Wert 5 unmöglich bei 1–3×1–3 außer… 1×5 gibt's nicht;
    mögliche Produkte: 1,2,3,4,6,9 → 5 tritt nie auf, kein Loch.)
- **`Schutzmassnahme`** — eine Maßnahme zu einer Gefährdung.
  Felder: `tenant_id`, `gefaehrdung_id`, `typ` (Massnahmentyp TOP), `beschreibung` (text),
  `verantwortlich` (string nullable), `frist` (date nullable), `umgesetzt_am` (date nullable),
  `wirksam_geprueft_am` (date nullable — § 3 Wirksamkeitskontrolle).
  - `istOffen(): bool` = `umgesetzt_am === null`.
  - `istWirksamGeprueft(): bool` = `wirksam_geprueft_am !== null`.

### Services (`app/Domains/Arbeitsschutz/Services/`)

- **`GbuFortschreiben`** — `handle(Gefaehrdungsbeurteilung $gbu, string $datum): void`:
  setzt `letzte_ueberpruefung_am` = **max(bisher, $datum)** (Max-Semantik — ein nachgetragenes älteres
  Überprüfungsdatum setzt die Frist nicht zurück), Status `Freigegeben`. `$datum` darf nicht in der Zukunft
  liegen (UI `before_or_equal:today`). Tenant über CurrentTenant.
- **`GbuMonitor`** — `status(): Collection`: tenant-scoped Übersicht je GBU mit `faelligkeitsStatus()`,
  `naechsteUeberpruefung()`, `hatOffeneMassnahmen()`. **Frist-Status aus EINER Methode** — keine divergente
  View-Query (SSOT-Lektion). `ueberfaelligeAnzahl(): int`.

## UI

Livewire **`app/Livewire/Arbeitsschutz/Gefaehrdungsbeurteilung.php`**, Route
`/arbeitsschutz/gefaehrdungsbeurteilung` Name `arbeitsschutz.gbu`, Gate `admin`/`pflegefachkraft` (wie
bestehender Arbeitsschutz-Nachweis-Screen), tenant-scoped (`CurrentTenant`, `ScopesTenantValidation`).
Funktionen:
- **GBU anlegen** (arbeitsbereich, taetigkeit, intervall, verantwortlich).
- **GBU-Liste** mit Frist-Ampel (überfällig rot „Fortschreibung seit X Tagen", bald gelb, grün) +
  Badge „N offene Maßnahme(n)" (aus `offeneMassnahmen()`) + höchste Risikostufe.
- **Gefährdung hinzufügen** (Faktor aus 6er-Dropdown mit § 5 Abs. 3-Bezug, Beschreibung,
  Wahrscheinlichkeit 1–3, Schwere 1–3 → Risikostufe sofort sichtbar).
- **Maßnahme hinzufügen** (TOP-Typ, Beschreibung, Verantwortlich, Frist).
- **Maßnahme als umgesetzt markieren** (`umgesetzt_am`, default heute, `before_or_equal:today`) +
  **Wirksamkeit prüfen** (`wirksam_geprueft_am`).
- **GBU fortschreiben** (Button → `GbuFortschreiben`, setzt Frist-Ampel zurück + Status Freigegeben).
- **GBU freigeben** (Status Entwurf → Freigegeben; aktiviert die Fortschreibungs-Frist).
Norm-Fußnote (§§ 5/6/3/4 ArbSchG). Nav: Gruppe „Qualität & Recht", Label „Gefährdungsbeurteilung",
zusätzlich Route in `$qualActive`-`routeIs(...)`-Liste eintragen.

## Kritische Dateien

- `app/Domains/Arbeitsschutz/**` — neue Domäne (Enums, Models, Services).
- `database/migrations/2026_06_2x_*` — 3 Tabellen (`gefaehrdungsbeurteilungen`, `gefaehrdungen`,
  `schutzmassnahmen`), je `foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()`, Kind-FKs
  `cascadeOnDelete`.
- `app/Livewire/Arbeitsschutz/Gefaehrdungsbeurteilung.php` + `resources/views/livewire/arbeitsschutz/gefaehrdungsbeurteilung.blade.php`.
- `routes/web.php` — Route im selben `auth`-Block wie `arbeitsschutz.nachweise`.
- `resources/views/layouts/app.blade.php` — Nav-Eintrag + `$qualActive`.
- `app/Domains/Identity/Database/Seeders/DemoSeeder.php` — Demo-GBU (z. B. „Pflege WB 1" mit
  biologischer Gefährdung [Infektion], muskuloskelettaler [Heben], psychischer [Schichtarbeit];
  je 1 offene + 1 umgesetzte Maßnahme; eine überfällige Fortschreibung für die rote Ampel).
- `docs/arbeitsschutz-gefaehrdungsbeurteilung.md`, Wiki-Seite, Memory.

## Tests (Pest, TDD)

- **Enums:** `Gefaehrdungsfaktor::nummer/paragraph` (6 Fälle), `Massnahmentyp::rang` (T<O<P).
- **Risiko:** `risikowert` = w×s; `risikostufe` Schwellen (1→gering, 4→mittel, 6/9→hoch).
- **Frist-Ampel:** Entwurf → immer 'gruen' (keine Frist); Freigegeben + letzte vor >Intervall → 'rot';
  ≤30 Tage → 'gelb'; frisch → 'gruen'; `letzte_ueberpruefung_am` null → fällt auf `erstellt_am` zurück.
- **SSOT:** GBU mit Gefährdung+offener Maßnahme → `offeneMassnahmen()` enthält sie, `hatOffeneMassnahmen()` true;
  nach `umgesetzt_am` gesetzt → leer/false. Maßnahmen **über mehrere Gefährdungen** korrekt aggregiert.
- **`GbuFortschreiben`:** setzt `letzte_ueberpruefung_am` als Max (älteres Datum setzt nicht zurück),
  Status Freigegeben; Zukunftsdatum → in UI Validierungsfehler (`before_or_equal:today`-Test im UI-Test).
- **`GbuMonitor`:** tenant-scoped (fremder Tenant unsichtbar), `ueberfaelligeAnzahl()`.
- **UI/Livewire:** GBU anlegen → in Liste; Gefährdung+Maßnahme hinzufügen; Maßnahme umgesetzt → Badge sinkt;
  fortschreiben → Ampel grün; **Gate-403** (Rolle ohne Recht); **IDOR** (fremd-Tenant-GBU nicht editierbar);
  Zukunftsdatum bei Umsetzung/Fortschreibung → Validierungsfehler.
- **Volle Suite** `php -d memory_limit=1G vendor/bin/pest` grün, **Larastan L5** clean, **Pint** clean.

## Risiken & Trade-offs

- **Abgrenzung zu `Personnel\Arbeitsschutz` (Nachweise):** bewusst getrennt — GBU begründet die Maßnahmen,
  Nachweise belegen deren personenbezogene Umsetzung (z. B. Unterweisung). Kein FK-Kopplung in v1 (YAGNI);
  Doku verweist wechselseitig. Spätere Iteration kann eine personenbezogene TOP-Maßnahme an einen NachweisTyp
  knüpfen.
- **Risiko-Matrix Nohl-light (3×3):** bewusst einfach gehalten statt 5×5/Kinney — deckt die „beurteilen"-Pflicht
  ab, ohne Scheingenauigkeit. Erweiterbar.
- **Frist nur bei Freigegeben scharf:** ein Entwurf ohne Freigabe soll nicht fälschlich „überfällig" rot werden.
  Bewusst: erst Freigabe startet die Fortschreibungs-Uhr.
