# Warenwirtschaft — Normbereiche (Charge/MHD, Pflegehilfsmittel, Gefahrstoffe, Beschaffung)

Aufbauend auf der FIFO-/Inventur-Basis (`docs/inventur-bewertung.md`) deckt die Warenwirtschaft vier weitere
Normbereiche ab. Alle setzen auf der FIFO-Spine `Lagerschicht` (Lot) → `Schichtabgang` (append-only) auf.

## Charge/MHD-Rückverfolgung & Lieferanten (Art. 18 VO (EG) 178/2002)

Jeder Wareneingang kann **Lieferant**, **Charge/Los-Nr.** und **MHD** erfassen (`Lieferant`-Stammdaten,
`lieferant_id`/`charge_nr`/`mhd` an der `Lagerschicht`).

- **„Eine Stufe zurück" (Pflicht):** Die Pflegeheim-Großküche ist Lebensmittelunternehmer (Art. 3 Nr. 3). Art. 18
  verlangt, jederzeit angeben zu können, **von welchem Lieferanten** eine Ware stammt.
- **„Eine Stufe vor" (kein Pflicht-Element, interner Mehrwert):** Da an Endverbraucher (Bewohner) abgegeben wird,
  entfällt die gesetzliche Vorwärts-Verfolgung. Wir bauen sie dennoch als **internen Rückruf** (welcher
  Bewohner/welche Abteilung hat eine betroffene Charge erhalten) — über den bewohnerbezogenen `Schichtabgang`.
- `Chargenverfolgung::verfolge(charge, tenant)` liefert je Charge: Lieferant (zurück) + Abgangskette mit
  Bewohner/Abteilung/Datum (vor). `MhdMonitor::ablaufend()` listet offene Schichten mit ablaufendem/abgelaufenem
  MHD (Ampel). UI: Livewire `Rueckverfolgung` (Route `rueckverfolgung`).
- **Aufbewahrung** (Doku-Hinweis, kein Auto-Löschen): BVL-Empfehlung 5 Jahre; bei kurz-MHD-Artikeln MHD + 6 Monate.

## Pflegehilfsmittel-Verbrauch (§ 40 SGB XI)

Bewohnerbezogener Verbrauch „zum Verbrauch bestimmter Pflegehilfsmittel" (Produktgruppe 54 des
GKV-Hilfsmittelverzeichnisses). `Warenverbrauch` nimmt optional einen Bewohner auf; der `Schichtabgang` trägt die
`resident_id`. `PflegehilfsmittelMonitor` summiert je Bewohner je Monat und ampelt gegen die 42-€-Referenz.

> **Rechtskontext (ehrlich):** § 40 Abs. 2 SGB XI deckelt die **Pflegekassen-Pauschale** (42,00 €/Monat, Stand
> 2025/2026) für PG-54-Verbrauchshilfsmittel — **nur ambulant/häuslich**. **Vollstationäre Heimbewohner haben
> keinen Anspruch** darauf; der Träger trägt diese Mittel über den Pflegesatz. Die Auswertung ist daher für
> stationäre Bewohner eine **interne Kostentransparenz**, kein Erstattungsanspruch. Die Livewire-Seite
> (Route `pflegehilfsmittel`) weist diesen Kontext sichtbar aus.

## Gefahrstoffverzeichnis (§ 6 Abs. 12 GefStoffV)

Gesetzlich gefordertes Verzeichnis der Gefahrstoffe. Pro Artikel mit `gefahrstoff = true` ein `Gefahrstoff`-Eintrag
mit den fünf Pflichtangaben (§ 6 Abs. 12 Nr. 1–5): Bezeichnung (= Artikel), Einstufung nach CLP-VO 1272/2008
(H-Sätze), Mengenbereich, Arbeitsbereiche, Verweis auf das **Sicherheitsdatenblatt** (SDB, Art. 31 REACH; als
PDF in der Media-Collection `sdb`, mit Versionsdatum). Compliance-maximal zusätzlich (TRGS 510/555): Signalwort,
GHS-Piktogramme, P-Sätze, Lagerort, Verweis auf die **Betriebsanweisung** (§ 14 GefStoffV). UI: Livewire
`Gefahrstoffverzeichnis` (Route `gefahrstoffe`), druck-/lesbar als Nachweis.

## Beschaffung / Bestellwesen

Geordneter Einkauf: `Bestellung` (Lieferant, Status) + `Bestellposition` (Menge bestellt/geliefert). Der
**Wareneingang gegen eine Bestellposition** (`BestellungWareneingang`) bucht über den normalen `Wareneingang`
(FIFO-Schicht inkl. Lieferant + `bestellposition_id`), erhöht `menge_geliefert` und führt den Bestell-Status nach
(`Bestellt → TeilweiseGeliefert → Geliefert`). Über die offene Menge hinaus wird eine Exception geworfen (kein
stilles Klemmen). `BedarfsVorschlag` schlägt Bestellmengen aus dem Unterbestand vor. UI: Livewire `Beschaffung`
(Route `beschaffung`); der spontane Direkt-Wareneingang in der Buchhaltung bleibt erhalten.

## Norm-Anker

- **Art. 18 VO (EG) Nr. 178/2002** (konsolidiert 26.05.2021) — Rückverfolgbarkeit (one step back/forward).
- **§ 40 Abs. 2 SGB XI** — zum Verbrauch bestimmte Pflegehilfsmittel, 42 €/Monat (nur ambulant), PG 54.
- **§ 6 Abs. 12 GefStoffV** (Fassung 17.12.2025) — Gefahrstoffverzeichnis; **§ 14** — Betriebsanweisung;
  **Art. 31 REACH** — SDB; **CLP-VO (EG) 1272/2008** — GHS-Einstufung.

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-wawi-normen-erweiterung-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-wawi-normen-erweiterung.md`
