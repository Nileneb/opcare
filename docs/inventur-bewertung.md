# Inventur & Vorratsbewertung (FIFO)

Die Warenwirtschaft bewertet ihre Vorräte nach dem **FIFO-Verfahren** (Verbrauchsfolge, § 256 HGB) und
unterstützt eine **Inventur-Kampagne** (§§ 240/241 HGB i. V. m. PBV). Beides bildet das Fundament für den
weiteren WaWi-Ausbau (Beschaffung, Pflegehilfsmittel-Versorgung, Chargen-Rückverfolgung).

## FIFO-Schichten

Jeder **Wareneingang** legt eine `Lagerschicht` (Lot) an: Eingangsmenge, Restmenge und Einstandspreis zum
Zeitpunkt der Lieferung (`charge_nr`/`mhd` sind für den späteren Chargen-/MHD-Ausbau vorbereitet). Der
**Warenverbrauch** zehrt die ältesten Schichten zuerst ab und schreibt je angezehrter Schicht einen
unveränderlichen `Schichtabgang` (Menge × tatsächlicher Einstandspreis). Die Verbrauchsbuchung trifft damit
die **echten Schichtkosten** (Soll Abteilungs-Aufwand an Haben Warenbestand), nicht einen Mischpreis.

> Reicht der Bestand für einen Verbrauch nicht, wird eine Exception geworfen — bewusst **kein** stilles
> Klemmen. Bestandsdiskrepanzen korrigiert die Inventur.

Der **Bestandswert** eines Artikels ist `Σ Restmenge × Einstandspreis` über die offenen Schichten
(`App\Domains\Accounting\Support\Lagerwert`). Er erscheint je Artikel und als Summe in der Buchhaltungs-/
Warenwirtschafts-Ansicht.

## Inventur

1. **Starten** (`InventurStarten`): Stichtag, optional je Abteilung. Pro aktivem Artikel wird eine
   `Inventurposition` mit der aktuellen **Soll-Menge** (Snapshot) und dem Bewertungsschnitt angelegt.
2. **Zählen**: Ist-Menge je Position erfassen (Livewire-Route `inventur`).
3. **Abschließen** (`InventurAbschliessen`): je gezählter Position wird die Differenz gebucht —
   - **Schwund** (Ist < Soll): FIFO abzehren, *Inventurdifferenz an Warenbestand*.
   - **Mehrbestand** (Ist > Soll): neue Schicht zum Schnitt, *Warenbestand an Inventurdifferenz*.

   Der Artikelbestand wird auf das Ist abgeglichen, der **Bestandswert eingefroren**
   (`bestandswert_summe`), der Status auf *abgeschlossen* gesetzt (kein Doppel-Abschluss).

> **Nicht gezählte Positionen** (ohne Ist-Menge) werden **nicht** als 0-Differenz gebucht, sondern bleiben
> unverändert und werden im Abschluss-Report transparent als „nicht gezählt: N" ausgewiesen.

## Konten

| Nummer | Konto | Verwendung |
|---|---|---|
| 3980 | Warenbestand | Aktivkonto des Lagers |
| 4980 | Bestandsdifferenzen (Inventur) | Schwund (Aufwand) / Mehrbestand (Ertrag) |

## Norm-Anker

- **§ 256 HGB** — Bewertungsvereinfachung, FIFO als zulässige Verbrauchsfolge.
- **§§ 240/241 HGB** — Inventar/Inventur; § 241 erlaubt u. a. die permanente Inventur auf Basis der
  laufenden Lagerfortschreibung.
- **PBV** (Pflegebuchführungsverordnung) verweist für die Inventur auf §§ 240/241 HGB.

## Spec & Plan

- Spec: `docs/superpowers/specs/2026-06-07-fifo-bewertung-inventur-design.md`
- Plan: `docs/superpowers/plans/2026-06-07-fifo-bewertung-inventur.md`
