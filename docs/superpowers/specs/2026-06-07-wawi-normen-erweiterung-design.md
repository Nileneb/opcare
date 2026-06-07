# WaWi-Normen-Erweiterung (#1 Gefahrstoff, #2 Pflegehilfsmittel, #3 Beschaffung, #5 Charge/MHD)

**Datum:** 2026-06-07
**Status:** Design — baut auf dem FIFO-/Inventur-Fundament (`2026-06-07-fifo-bewertung-inventur-design.md`) auf.

## Ziel

Die Warenwirtschaft compliance-maximal um vier Normbereiche erweitern, die alle auf der bestehenden
FIFO-Spine (`Lagerschicht` → `Schichtabgang`) aufsetzen:

| # | Norm | Kern |
|---|---|---|
| 5 | Art. 18 VO (EG) 178/2002 | Rückverfolgbarkeit: eine Stufe zurück (Lieferant) + eine Stufe vor (Verbrauchsort/Bewohner); Charge/MHD scharf |
| 2 | § 40 SGB XI | bewohnerbezogener Verbrauch „zum Verbrauch bestimmter Pflegehilfsmittel" + Monats-Pauschale-Überwachung |
| 1 | § 6 GefStoffV | Gefahrstoffverzeichnis (GHS/CLP-Einstufung, Mengenbereich, Arbeitsbereich, SDB-Verweis) |
| 3 | Beschaffung/Bestellwesen | Bestellung → Wareneingang gegen Bestellposition (Teillieferung), Bedarfsvorschlag aus Unterbestand |

## Architektur-Leitplanken (Ist-Stand, verbindlich)

- Bounded Context `app/Domains/Accounting`. FIFO-Spine: `Wareneingang` legt `Lagerschicht` (Lot, mit
  `charge_nr`/`mhd` — Felder existieren bereits, aber UI/Workflow fehlen). `Warenverbrauch` zehrt FIFO ab und
  schreibt je Schicht einen unveränderlichen `Schichtabgang`.
- `BaseModel` = `BelongsToTenant` (auto `tenant_id`) + `LogsActivity`. Modelle, deren Felder häufig mutieren
  oder reine Append-Logs sind (Vorbild `Lagerschicht`/`Schichtabgang`), nutzen nur
  `use App\Domains\Identity\Concerns\BelongsToTenant;`.
- Livewire 4: `#[Layout('layouts.app')]`, `abort_unless`-Gates in jeder Aktion, Method-Injection in
  `render()`/Aktionen, `wire:model`/`wire:click`/`wire:confirm`.
- Tests: Pest, `Model::create([...])` direkt (keine Factories für Accounting), `app(CurrentTenant::class)->set($tenant)`,
  `Role::findOrCreate('buchhaltung')` für Rollen-Gates. PHPStan level 5
  (`php -d memory_limit=1G vendor/bin/phpstan analyse`), Pint clean.
- **Kein stilles Schlucken** (Projektregel): Über-Verbrauch wirft Exception; nicht-prüfbares → transparent
  als „nicht ausgewertet (Grund)" ausweisen, niemals als „ok" faken.
- ide-helper Docblocks: `printf 'no\n' | php artisan ide-helper:models -W -R "FQCN" ...` (positional, KEIN `-M`).

## Build-Reihenfolge & Abhängigkeiten

Die Features teilen sich die Spine, daher diese Reihenfolge:

1. **#2 Pflegehilfsmittel** — fügt `resident_id` (nullable) am `Schichtabgang` + optionalen Bewohner am
   `Warenverbrauch` hinzu (= die „eine Stufe vor"-Hälfte der Rückverfolgung). Liefert die Bewohner-Zuordnung,
   die #5 in der Vorwärts-Verfolgung anzeigt.
2. **#5 Charge/MHD + Lieferant** — `Lieferant`-Modell (= „eine Stufe zurück"), `lieferant_id` an der
   `Lagerschicht`, Verdrahtung von Charge/MHD/Lieferant in die Wareneingang-UI, MHD-Monitor + Chargen-/
   Rückverfolgungs-Ansicht (zeigt dank #2 den Bewohner als Abnehmer).
3. **#1 Gefahrstoffverzeichnis** — orthogonal: `Gefahrstoff` (hasOne am `Artikel`) mit GHS/CLP-Einstufung,
   Mengenbereich, Arbeitsbereich, Lagerort, Betriebsanweisungs-Verweis + SDB als Media; Verzeichnis-UI.
4. **#3 Beschaffung** — `Lieferant` (aus #5) wiederverwendet: `Bestellung` + `Bestellposition`, Bestell-UI mit
   Bedarfsvorschlag aus Unterbestand, Wareneingang gegen Bestellposition (Teillieferung, Status).

---

## #2 Pflegehilfsmittel-Versorgung (§ 40 SGB XI)

**Norm:** § 40 Abs. 2 SGB XI — „zum Verbrauch bestimmte Pflegehilfsmittel" (Produktgruppe 54 des
GKV-Hilfsmittelverzeichnisses, z. B. Einmalhandschuhe, Bettschutzeinlagen, Desinfektionsmittel). Monatliche
Pauschale **42,00 €/Monat** (seit 01.01.2025, 2026 unverändert; Quelle gesetze-im-internet.de §40 SGB XI,
GKV-Spitzenverband).

> **WICHTIGE Compliance-Klarstellung (Research bestätigt):** § 40 Abs. 2 SGB XI gilt nur für **ambulante/
> häusliche** Versorgung. **Vollstationäre Heimbewohner haben KEINEN Anspruch** auf die 42-€-Pauschale — der
> Träger trägt diese Verbrauchsmittel über den Pflegesatz. Das Feature darf daher **keinen Pflegekassen-Anspruch
> für stationäre Bewohner faken**. Es bildet zwei ehrliche Zwecke ab: (a) interne bewohnerbezogene
> Verbrauchs-/Kostentransparenz (stationär), (b) korrekte 42-€-Deckel-Überwachung für **ambulant** betreute
> Klienten. Der Monitor vergleicht gegen die 42-€-Referenz, beschriftet aber den Rechtskontext (ambulant vs.
> stationär) und stellt die Pauschale nur dann als „Anspruch" dar, wenn der Bewohner als ambulant markiert ist.

**Datenmodell:**
- `Artikel`: neues Feld `pflegehilfsmittel` (bool, default false) + `pg_nummer` (string nullable, Produktgruppe/
  Positionsnr. des Hilfsmittelverzeichnisses, z. B. „54.40.01").
- `Schichtabgang`: neues Feld `resident_id` (nullable FK `residents`, `nullOnDelete`). Bewohnerbezogener
  Verbrauch; null = anonymer/abteilungsbezogener Verbrauch (Status quo bleibt gültig).
- `Warenverbrauch::handle(...)`: optionaler Parameter `?int $residentId = null`, wird auf jeden in dieser
  Bewegung erzeugten `Schichtabgang` geschrieben. Keine Buchungslogik-Änderung.

**Auswertung:** `PflegehilfsmittelMonitor` (Support): je Bewohner je Monat Summe `menge × einstandspreis` der
`Schichtabgang` mit `resident_id` gesetzt UND Artikel `pflegehilfsmittel = true`; Ampel gegen die 42-€-**Referenz**
(grün < 80 %, amber < 100 %, rot ≥ 100 %). Konstante `PflegehilfsmittelMonitor::PAUSCHALE = 42.00`. Die Ampel ist
eine **interne Kosten-Referenz**, kein Pflegekassen-Anspruch — siehe Compliance-Klarstellung oben.

**UI:** `Buchhaltung::verbrauch()` erhält eine optionale Bewohner-Auswahl (nur wenn gewählter Artikel
`pflegehilfsmittel`). Neue Livewire-Seite `Pflegehilfsmittel` (Route `pflegehilfsmittel`, Gate
admin/buchhaltung/pflegefachkraft): Monatswähler + Tabelle Bewohner × verbrauchter Wert (Ampel). Die Seite trägt
einen **deutlich sichtbaren Rechtskontext-Hinweis**: „§ 40 Abs. 2 SGB XI deckelt die Pflegekassen-Pauschale für
zum Verbrauch bestimmte Pflegehilfsmittel (PG 54) auf 42 €/Monat — nur **ambulant/häuslich**. Bei vollstationärer
Pflege trägt der Träger diese Mittel über den Pflegesatz; diese Auswertung dient dann der internen
Kostentransparenz." Kein Wort von „Erstattung/Anspruch" für stationäre Bewohner.

---

## #5 Charge/MHD-Rückverfolgung (Art. 18 VO (EG) 178/2002) + Lieferant

**Norm:** Art. 18 VO (EG) 178/2002 (konsolidiert 26.05.2021) — Rückverfolgbarkeit. Die Pflegeheim-Großküche ist
Lebensmittelunternehmer (Art. 3 Nr. 3): **„one step back" (Lieferant) ist Pflicht**. **„one step forward" entfällt**,
weil an Endverbraucher (Bewohner) abgegeben wird (Research bestätigt). Die Vorwärts-Verfolgung zum Bewohner/zur
Abteilung bauen wir trotzdem als **internen Rückruf-Mehrwert** (Produktrückruf: wer hat die betroffene Charge
erhalten) — als Plus deklariert, nicht als Art.-18-Pflicht. Charge/Los (RL 2011/91/EU) + MHD = Verknüpfungsschlüssel.
Aufbewahrung der Rückverfolgungsdaten: BVL-Empfehlung 5 Jahre (kurz-MHD: MHD + 6 Monate) — als Doku-Hinweis, kein
Auto-Löschen.

**Datenmodell:**
- `Lieferant` (neu, `BelongsToTenant` + `LogsActivity` = `BaseModel`): `name`, `anschrift` (nullable),
  `kontakt` (nullable), `lieferantennr`/`gln` (nullable). Stammdaten der „Stufe zurück".
- `Lagerschicht`: neues Feld `lieferant_id` (nullable FK, `nullOnDelete`). Charge/MHD existieren bereits.
- `Wareneingang::handle(...)`: neuer optionaler Parameter `?int $lieferantId = null` → auf die Schicht.

**Support:**
- `Chargenverfolgung::verfolge(string $chargeNr, int $tenantId): array` — alle `Lagerschicht` mit dieser
  `charge_nr` + Lieferant (zurück) + alle `Schichtabgang` der Schicht mit Bewegung (Datum, Abteilung, Notiz)
  und — dank #2 — Bewohner (vor). Vollständige Rückruf-Sicht.
- `MhdMonitor::ablaufend(int $tenantId, int $tageVorlauf = 14): Collection` — offene Schichten
  (`menge_rest > 0`) mit `mhd <= today()+vorlauf`, sortiert nach MHD; `mhd < today` = abgelaufen (rot).

**UI:**
- `Buchhaltung::wareneingang()` + Form: Felder `charge_nr`, `mhd`, `lieferant` (Select) erfassen und an die
  Action durchreichen (Action kann es bereits, UI bisher nicht). Lieferanten-Stammdaten-Mini-CRUD.
- Neue Livewire-Seite `Rueckverfolgung` (Route `rueckverfolgung`, Gate admin/buchhaltung/kueche):
  MHD-Ablauf-Liste (Ampel) + Chargen-Suchfeld → Treffer mit Lieferant (zurück) und Verbrauchs-/Bewohner-Liste (vor).

---

## #1 Gefahrstoffverzeichnis (§ 6 GefStoffV)

**Norm:** **§ 6 Abs. 12 GefStoffV** (Fassung 17.12.2025, in Kraft seit 20.12.2025) — der Arbeitgeber führt ein
**Verzeichnis der Gefahrstoffe**. Die fünf gesetzlichen Pflichtangaben je Gefahrstoff (§ 6 Abs. 12 Nr. 1–5):
(1) Bezeichnung, (2) Einstufung nach CLP-VO (EG) 1272/2008 bzw. gefährliche Eigenschaften (H-Sätze), (3)
Mengenbereich im Betrieb, (4) Arbeitsbereiche mit möglicher Exposition, (5) Verweis auf das Sicherheitsdatenblatt
(SDB, Art. 31 REACH) inkl. dessen Versionsdatum. Alle Angaben außer der Menge müssen für Beschäftigte zugänglich
sein (§ 6 Abs. 12 S. 3). Ergänzend compliance-maximal (TRGS 510/555): Signalwort + Piktogramme + P-Sätze,
Lagerort, sowie der Verweis auf die **Betriebsanweisung** (§ 14 GefStoffV — je Gefahrstoff Pflicht, separat).

**Datenmodell:**
- `Artikel`: neues Feld `gefahrstoff` (bool, default false) — Flag/Filter.
- `Gefahrstoff` (neu, `BaseModel`, `hasOne` vom Artikel, `implements HasMedia` via Spatie MediaLibrary):
  `artikel_id` (FK unique), `signalwort` (enum-string `gefahr`/`achtung`/null), `h_saetze` (json/text — Liste),
  `p_saetze` (json/text), `ghs_piktogramme` (json — z. B. `['GHS02','GHS07']`), `mengenbereich` (string,
  z. B. „< 1 l", „1–10 l"), `arbeitsbereiche` (string/text), `lagerort` (string nullable), `betriebsanweisung`
  (text/url nullable), `sdb_version_datum` (date nullable — Versionsdatum des referenzierten SDB).
  SDB als Media-Collection `sdb` (PDF).
- Enum `GhsPiktogramm` (GHS01–GHS09 mit Label) für saubere Auswahl.

**UI:** Livewire `Gefahrstoffverzeichnis` (Route `gefahrstoffe`, Gate admin/haustechnik/kueche/buchhaltung):
Liste aller Gefahrstoff-Artikel mit Piktogrammen/Signalwort/Mengenbereich/Arbeitsbereich/SDB-Download; Anlegen/
Bearbeiten je Eintrag inkl. SDB-Upload. Verzeichnis = der gesetzlich geforderte Nachweis (druck-/exportierbar).

---

## #3 Beschaffung / Bestellwesen

**Ziel:** Geordneter Einkauf statt direktem Wareneingang: Bestellung beim Lieferant, Wareneingang bucht gegen
offene Bestellpositionen (Teillieferungen möglich), Bedarfsvorschlag aus Unterbestand.

**Datenmodell:**
- `Bestellung` (neu, `BaseModel`): `lieferant_id` (FK), `bestelldatum`, `status` (enum
  `BestellStatus`: Entwurf/Bestellt/TeilweiseGeliefert/Geliefert/Storniert), `erstellt_von`, `notiz`.
- `Bestellposition` (neu, `BaseModel`): `bestellung_id`, `artikel_id`, `menge_bestellt`, `menge_geliefert`
  (default 0), `einzelpreis`. `offen()` = menge_bestellt − menge_geliefert > 0.
- `Lagerschicht`: optional `bestellposition_id` (nullable FK) zur Verknüpfung Eingang↔Bestellung.

**Actions:**
- `BestellungAnlegen::handle(int $lieferantId, array $positionen, ?int $userId, ?string $notiz): Bestellung` —
  Positionen `[artikel_id, menge, preis]`. Status `Bestellt`.
- `BestellungWareneingang::handle(Bestellposition $pos, float $menge, ?float $preis, string $datum,
  ?string $chargeNr, ?string $mhd): Lagerbewegung` — ruft `Wareneingang` (FIFO-Schicht inkl. Lieferant aus der
  Bestellung) und erhöht `menge_geliefert`; aktualisiert Bestellungs-Status (Teil-/Volllieferung). Über-Lieferung
  über die Restmenge hinaus → Exception (kein stilles Klemmen).
- `BedarfsVorschlag::fuer(int $tenantId, ?int $lieferantId): Collection` — Artikel im Unterbestand
  (`mindestbestand` gesetzt, `bestand < mindestbestand`), Vorschlagsmenge = `mindestbestand − bestand`
  (mind. 1), als Bestellvorschlag.

**UI:** Livewire `Beschaffung` (Route `beschaffung`, Gate admin/buchhaltung): Bestellungen-Liste + Status,
neue Bestellung (Lieferant + Positionen, „Bedarf übernehmen"-Button aus Unterbestand), Wareneingang gegen
offene Position (Charge/MHD beim Eingang). Bestehender Direkt-Wareneingang in `Buchhaltung` bleibt für
spontane Eingänge erhalten.

## Konten

Keine neuen Konten nötig — #3 nutzt weiter `Wareneingang` (Warenbestand an Verbindlichkeiten), #2/#5/#1 sind
Stammdaten/Auswertung ohne eigene Buchungssätze.

## Norm-Anker (Belege)

- **Art. 18 VO (EG) Nr. 178/2002** — Rückverfolgbarkeit (one step back/forward).
- **§ 40 Abs. 2 SGB XI** — zum Verbrauch bestimmte Pflegehilfsmittel, Monats-Pauschale (PG 54).
- **§ 6 GefStoffV** — Gefahrstoffverzeichnis; **§ 14 GefStoffV** — Betriebsanweisung; **Art. 31 REACH** — SDB;
  **CLP-VO (EG) 1272/2008** — GHS-Einstufung/H-Sätze/Piktogramme.

## Verifikation

Pro Feature: Pest-Tests (Model/Action/Support + Livewire-Smoke), volle Suite grün als Gate zwischen Features,
PHPStan 0, Pint clean, `migrate:fresh --seed` ok, DemoSeeder erweitert (sichtbare Daten je Feature), Screenshot
je neuer Seite. README-Testzähler + `docs/` + Wiki nachziehen.
