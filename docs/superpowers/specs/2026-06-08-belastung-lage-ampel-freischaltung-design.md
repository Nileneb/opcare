# Belastungs-Lage-Ampel (0-10 Farbverlauf) + Beschluss-Freischaltung (Mode B/C) — Design

**Goal:** (A) Die Belastungs-Ampel von starren 4 Stufen auf einen **weichen 0-10-Farbverlauf** (rein farblich,
10=grün=gut) umstellen. (B) Eine **individuelle Selbst-Ampel** + **Selbst-Meldung an die Leitung** (Mode C) bauen,
die **erst nach einem Mitarbeitenden-Beschluss** (Voting) freigeschaltet sind — der BetrVG-Mitbestimmungsweg in der App.

## Teil A — Lage-Ampel als 0-10-Farbverlauf

- **Skala = „Lage/Wohlbefinden", 10 = grün = gut**, invertiert zur Roh-Belastung: `lage = clamp(0..10, round((100 - score) / 10))`.
  Anzeige **rein farblich, keine Zahl** (die 0-10 ist die interne Skalierung). Ersetzt das `Belastungsstufe`-Badge
  in der Anzeige.
- **Farbverlauf** mit ineinander überlaufenden Farben: piecewise-lineare HSL-Hue-Interpolation —
  0-2 rot (Hue 0°), ~4-5 gelb (Hue ~50°), 7-10 grün (Hue ~120°), dazwischen geblendet. Helper
  `BelastungsAmpel::farbe(int $lage): string` → `hsl(H, 75%, 45%)` (Anker: 0→0°, 2→0°, 4.5→50°, 6→75°, 8→110°,
  10→120°; linear interpoliert). Render als farbiger Balken/Punkt.
- **Meldepflicht bleibt intern** am `score`/`Belastungsstufe` (`istMeldepflichtig()`); nur die Darstellung ändert sich.
- `BelastungsBefund` (DTO) bekommt `int $lage`; `Belastungsmeldung` leitet `lage` aus `score` ab (Accessor/Methode).
- Betroffen: Dienstplan-Belastungs-Panel + GBU-Belastungsmeldungen-Sektion — Badge → Farbverlauf-Anzeige.

## Teil B — Beschluss-Freischaltung + individuelle Selbst-Ampel + Selbst-Meldung

### Freischaltung (Voting-gekoppelt)

- **`BelastungFreischaltung`** (BaseModel — Governance-Audit, KEINE Personendaten): `tenant_id`,
  `abstimmung_id` (FK voting `abstimmungen`, der freischaltende Beschluss), `freigeschaltet_von` (FK users),
  `freigeschaltet_am` (date), `zurueckgenommen_am` (date nullable), `zurueckgenommen_von` (nullable).
  `istAktiv(): bool` (`zurueckgenommen_am === null`).
- **`BelastungFreischalten`** (Service, inject CurrentTenant): `ausBeschluss(Abstimmung $a, User $u): BelastungFreischaltung`
  — prüft `$a->art === Abstimmungsart::Beschluss`, `$a->elektorat === Elektorat::Mitarbeitende`,
  `$a->status === AbstimmungStatus::Geschlossen` und **angenommen** (Ja-Mehrheit via `Auszaehlung::ergebnis`);
  sonst Ausnahme. Legt aktive Freischaltung an (deaktiviert vorherige). `zuruecknehmen(User $u)` setzt
  zurueckgenommen_am (Rücknahme; ein neuer Gegen-Beschluss kann ebenfalls zurücknehmen).
- **`BelastungFreischaltung::aktivFuer(int $tenantId): bool`** — die EINE Quelle, ob Mode B/C scharf sind.

### Individuelle Selbst-Ampel (Mode B, Selbstfürsorge)

- **`PersoenlicheBelastung`** (`extends Model` + `use BelongsToTenant`, **KEIN LogsActivity** — privat, wie
  `Personnel\Energielevel`): `tenant_id`, `user_id`, `wert` (int 0-10), timestamps. Jüngster Eintrag je User = aktuell.
- **`PersoenlicheBelastungSetzen`** (Service): `handle(User $u, int $wert): PersoenlicheBelastung` — **nur wenn
  `BelastungFreischaltung::aktivFuer` true**, sonst 403. wert 0-10. Jede:r setzt NUR den eigenen Wert.
- Sichtbar nur für die Person selbst (eigener Wert + Verlauf der letzten Tage optional). Kein Vorgesetzten-Einblick
  in den Roh-Wert.

### Selbst-Meldung an die Leitung (Mode C, selbst-initiiert)

- **`SelbstmeldungUeberlastung`** (BaseModel): `tenant_id`, `user_id` (der/die meldende MA — **selbst ausgelöst**,
  daher named zulässig), `wert` (0-10 Schnappschuss), `notiz` (nullable), `gemeldet_am` (date),
  `quittiert_von`/`quittiert_am` (nullable). `istOffen()`.
- **`UeberlastungMelden`** (Service): `handle(User $u, ?string $notiz): SelbstmeldungUeberlastung` — **nur wenn
  Freischaltung aktiv**; legt Meldung an (wert = jüngste PersoenlicheBelastung) + Notification `SelbstUeberlastung`
  an `admin`/`super-admin`. Dedupe: nur eine offene je User.
- **Wichtig:** Mode C ist hier **ausschließlich selbst-initiiert** (MA drückt den Knopf) — keine automatische
  Personen-Überwachung. Trotzdem hinter dem Team-Beschluss, wie vom User gewünscht.

### UI

- **Selbst-Ampel + Melden** als Sektion auf dem **Energiebarometer-Screen** (`Personnel\Energiebarometer`,
  Self-Care-Geschwister), **nur sichtbar wenn `aktivFuer` true** (sonst Hinweis „durch Team-Beschluss freischaltbar").
  0-10-Slider (`wire:model`), Live-Farbverlauf-Anzeige, „Überlastung an Leitung melden"-Button.
- **Freischaltungs-Verwaltung** (admin) auf der **Arbeitsrecht-Seite** (`Scheduling\Arbeitsrecht`, wo der
  BelastungsKonfig-Editor ist): Status (aktiv/inaktiv + welcher Beschluss), „aus geschlossenem Beschluss
  freischalten" (Dropdown geschlossener+angenommener Mitarbeitenden-Beschlüsse) + „zurücknehmen". Admin-gated.
- **Offene Selbstmeldungen + Quittieren** auf dem GBU-Screen (neben den Wohnbereich-Belastungsmeldungen), SSOT
  `quittiert_am IS NULL`.

## Kritische Dateien

- `app/Domains/Arbeitsschutz/Support/BelastungsAmpel.php` (Farbverlauf-Helper) + `Data/BelastungsBefund.php` (lage)
  + `Models/Belastungsmeldung.php` (lage-Accessor).
- `app/Domains/Arbeitsschutz/Models/{BelastungFreischaltung,PersoenlicheBelastung,SelbstmeldungUeberlastung}.php`
  + `Services/{BelastungFreischalten,PersoenlicheBelastungSetzen,UeberlastungMelden}.php`
  + `Notifications/SelbstUeberlastung.php`.
- Migrations: `belastung_freischaltungen`, `persoenliche_belastungen`, `selbstmeldungen_ueberlastung`.
- `app/Livewire/Scheduling/Dienstplan.php`+Blade, `app/Livewire/Arbeitsschutz/Gefaehrdungsbeurteilung.php`+Blade
  (Farbverlauf + Selbstmeldungen), `app/Livewire/Personnel/Energiebarometer.php`+Blade (Selbst-Ampel),
  `app/Livewire/Scheduling/Arbeitsrecht.php`+Blade (Freischaltung).

## Pflicht-Lektionen (Review-Blocker)

- **Freischalt-Gate:** `PersoenlicheBelastungSetzen`/`UeberlastungMelden` MÜSSEN `aktivFuer` prüfen (403 sonst) —
  in Service UND UI. Eine Quelle (`BelastungFreischaltung::aktivFuer`).
- **Kein fremder Einblick:** PersoenlicheBelastung nur eigener Wert; Vorgesetzte sehen nur die **selbst gemeldete**
  Überlastung, nie den Roh-Slider-Verlauf. Kein LogsActivity auf PersoenlicheBelastung.
- **Selbst-Initiierung Mode C:** keine automatische Personen-Meldung; nur per Knopf der betroffenen Person.
- **SSOT** offene Selbstmeldungen (`quittiert_am IS NULL`); **Dedupe** je User; **Tenant/IDOR**; Gate je Schreibmethode.
- **Lage-Invertierung korrekt:** lage = round((100-score)/10), geclamped 0-10; Farbe stetig.
- Beschluss-Prüfung echt (art/elektorat/status/angenommen) — keine Freischaltung ohne angenommenen Beschluss.

## Tests (Pest, TDD)

- **Lage/Farbe:** lageAusScore (score 0→10, 100→0, 50→5); `BelastungsAmpel::farbe` (0/2 rot-Hue≈0, ~4-5 gelb, 8-10 grün-Hue≈110-120), stetig/monoton.
- **BelastungFreischalten:** angenommener Mitarbeitenden-Beschluss → aktiv; abgelehnter/offener/falsche Art →
  Ausnahme, keine Freischaltung; `aktivFuer` spiegelt Zustand; zuruecknehmen → inaktiv.
- **PersoenlicheBelastungSetzen:** ohne Freischaltung 403; mit → speichert eigenen Wert; fremden Wert setzen nicht möglich.
- **UeberlastungMelden:** ohne Freischaltung 403; mit → Meldung + Notification (fake); Dedupe (zweite offene → keine).
- **UI:** Selbst-Ampel nur bei Freischaltung sichtbar; Slider speichert; Melden erzeugt Meldung; Admin schaltet
  aus Beschluss frei/zurück; GBU listet+quittiert Selbstmeldung; Dienstplan/GBU zeigen Farbverlauf statt Badge;
  Gate-403; IDOR.
- **Volle Suite** grün, **Larastan L5** clean, **Pint** clean.

## Risiken & Trade-offs

- **Mode C = nur selbst-initiiert** (kein Auto-Monitoring) — bewusst die datenschutzfreundlichste Variante, trotzdem
  hinter Team-Beschluss. Echtes Auto-Scoring je Person bleibt ungebaut (bräuchte zusätzlich DSFA).
- **Freischaltung an Beschluss gekoppelt** statt totem Flag → echter Aktivierungs-Pfad (kein „nie genutzt").
- **Farbverlauf ohne Zahl:** bewusst, um keine Pseudo-Präzision/kein Personen-Ranking zu suggerieren.
