# Design: FIFO-Vorratsbewertung + Inventur (WaWi-Fundament)

**Datum:** 2026-06-07
**Status:** ✅ Approved (User „ja", 2026-06-07) — bereit für writing-plans
**Domäne:** `app/Domains/Accounting`
**Norm-Anker:** §§ 240/241 HGB (Inventur/Inventar), § 256 HGB (FIFO-Verbrauchsfolge),
Pflegebuchführungsverordnung (PBV) verweist für die Inventur explizit auf §§ 240/241 HGB.

## Kontext

Die heutige Warenwirtschaft (`Artikel`, `Lagerbewegung`, `Wareneingang`, `Warenverbrauch`) führt einen
perpetuellen Mengen-Bestand und bewertet **implizit** zum *letzten Einkaufspreis*: `Wareneingang`
überschreibt `artikel.einkaufspreis`, `Warenverbrauch` bucht `menge × einkaufspreis`. Es gibt kein
dokumentiertes Bewertungsverfahren, keinen datierten Bestandswert und keine Inventur. `Warenverbrauch`
klemmt zudem still: `bestand = max(0, bestand − menge)` — ein Stummschalten (verstößt gegen die
Projektregel „NIEMALS Errors stumm schalten").

Dies ist das **Fundament** für den weiteren WaWi-Ausbau: #1 Gefahrstoffverzeichnis (GefStoffV § 6,
orthogonal), #2 Pflegehilfsmittel-Versorgung (§ 40 SGB XI), #3 Beschaffung/Bestellwesen,
#5 Chargen/Rückverfolgbarkeit (Art. 18 VO (EG) 178/2002). Bewertete, FIFO-geschichtete Bestände sind die
gemeinsame Basis, auf die #2/#3/#5 andocken.

## Gewähltes Verfahren: FIFO (§ 256 HGB)

User-Entscheidung: **FIFO**, nicht gleitender Durchschnitt oder Festwert. Genaueste Bewertung und
natürliche Brücke zu #5 (Charge/MHD an der Schicht). Mehr Modell-/Buchungsaufwand bewusst akzeptiert
(„Gesetzeskonformität ist ein großes Plus, auch wenn es mehr Programmieraufwand bedeutet").

## Scope

**Rein:** FIFO-Schichten-Ledger, Umbau `Wareneingang`/`Warenverbrauch` auf echte Schichtkosten,
Bestandswert aus Schichten, Inventur-Kampagne (Zählliste → Soll-Ist → Differenzbuchung),
Inventurdifferenz-Konto, eingefrorener Bestandswert-Snapshot, Eintrittspunkt-UI.

**Raus (Folge-Iterationen, sauber markiert):** #1 Gefahrstoff, #2 Pflegehilfsmittel, #3 Beschaffung,
#5 Charge/MHD-Workflow (Felder sind vorbereitet, Workflow nicht), Mehrlager/Lagerorte,
Point-in-time-Bewertung in der Vergangenheit jenseits eingefrorener Inventuren.

## Datenmodell

### `Lagerschicht` (FIFO-Lot, Tabelle `lagerschichten`)
`tenant_id`, `artikel_id`, `eingang_bewegung_id` (FK `lagerbewegungen`), `eingangsdatum` (date),
`menge_eingang` (decimal:2), `menge_rest` (decimal:2), `einstandspreis` (decimal:4 — feiner für
FIFO-Arithmetik), nullable `charge_nr` (string), nullable `mhd` (date).
Index `(artikel_id, eingangsdatum, id)` für FIFO-Reihenfolge (FEFO später über `mhd`).
**Kein `LogsActivity`** (Schicht-`menge_rest` mutiert bei jedem Abgang → Log-Spam); Audit liegt im
`Schichtabgang` + der `Lagerbewegung`.

**[D4]** `charge_nr`/`mhd` sind jetzt schon da (#5-ready), aber ohne #5-Workflow.

### `Schichtabgang` (Tabelle `schichtabgaenge`, **unveränderlich**)
`tenant_id`, `bewegung_id` (FK `lagerbewegungen`), `schicht_id` (FK `lagerschichten`), `menge`
(decimal:2), `einstandspreis` (decimal:4). Protokolliert, *welche* Schicht ein Verbrauch zu welchem
Preis gezehrt hat.
**[D5]** Das ist zugleich die Brücke für #2 (später `resident_id`) und #5 (Rückruf: welche Charge ging
wohin). Append-only.

### `Inventur` (Tabelle `inventuren`)
`tenant_id`, `abteilung` (nullable = ganzes Haus, Enum `Abteilung`), `stichtag` (date),
`status` (Enum `InventurStatus`: Offen/Abgeschlossen), `bestandswert_summe` (decimal:2, eingefroren bei
Abschluss), `differenz_buchung_id` (nullable FK `buchungen`), `erstellt_von`, `abgeschlossen_von`,
`abgeschlossen_am` (nullable).

### `Inventurposition` (Tabelle `inventur_positionen`)
`tenant_id`, `inventur_id`, `artikel_id`, `soll_menge` (Snapshot Σ Schicht-Rest bei Anlage, decimal:2),
`ist_menge` (nullable decimal:2 bis gezählt), `einstandspreis_schnitt` (decimal:4, für Differenzwert),
`differenz_menge`/`differenz_wert` (abgeleitet), `gezaehlt_von` (nullable), `gezaehlt_am` (nullable).

### Enum + Konto-Default
`InventurStatus` (Offen/Abgeschlossen). **[D3]** Neuer `AccountingDefaults::INVENTURDIFFERENZ = '4980'`
(KontoTyp Aufwand): Schwund = *Inventurdifferenz an Warenbestand*, Mehrbestand = *Warenbestand an
Inventurdifferenz* (Ertragswirkung über dasselbe Konto, KISS).

## Bewertungs- & Buchungsfluss

### `Wareneingang` (Umbau)
Zusätzlich zur bestehenden Logik eine `Lagerschicht` anlegen (`menge_eingang = menge_rest = menge`,
`einstandspreis = preis ?? artikel.einkaufspreis`, `eingangsdatum = datum`, optional `charge_nr`/`mhd`).
Buchung *Warenbestand an Verbindlichkeiten* (menge × preis) unverändert. `artikel.bestand += menge`.
**[D1]** `artikel.einkaufspreis` bleibt erhalten, aber nur noch als Anzeige-/Bestell-Default — die
Bewertung kommt ausschließlich aus den Schichten.

### `Warenverbrauch` (Umbau)
FIFO: offene Schichten (`menge_rest > 0`) nach `eingangsdatum, id` aufsteigend abzehren; je betroffener
Schicht einen `Schichtabgang` schreiben und `menge_rest` mindern; Gesamtkosten = Σ (Teilmenge ×
Schicht-Einstandspreis). Buchung *Abteilungs-Aufwand an Warenbestand* mit den **tatsächlichen
FIFO-Kosten** statt letztem Preis. `artikel.bestand −= menge`.
**[D2]** Reicht der verfügbare Bestand nicht (`Σ menge_rest < menge`), wirft die Action eine
`InvalidArgumentException` — ersetzt das heutige stille `max(0, …)`-Klemmen. Diskrepanzen korrigiert die
Inventur, nicht ein stilles Clamp.

### `Lagerwert` (neuer Support-Service)
`bestandswert(Artikel): float = Σ menge_rest × einstandspreis`;
`bestandswertGesamt(tenantId, ?Abteilung): float`. Aktueller (Live-)Wert; historische Werte liefern die
eingefrorenen Inventuren.

## Inventur-Prozess

1. **Start** (`InventurStarten`): `Inventur` anlegen (Stichtag, optional Abteilung), je aktivem Artikel
   eine `Inventurposition` mit `soll_menge` = aktuelle Σ Schicht-Rest und `einstandspreis_schnitt`
   (Bestandswert / Menge, sonst letzter Preis). `status = Offen`.
2. **Zählung**: `ist_menge` je Position erfassen (`gezaehlt_von`/`gezaehlt_am`).
3. **Abschluss** (`InventurAbschliessen`) je Position mit gesetzter `ist_menge`:
   - *Schwund* (`ist < soll`): `|Differenz|` FIFO abzehren (Lagerbewegung `typ = 'inventur'`,
     Schichtabgänge), Buchung *Inventurdifferenz an Warenbestand*.
   - *Mehrbestand* (`ist > soll`): neue `Lagerschicht` (`menge = Differenz`,
     `einstandspreis = einstandspreis_schnitt`, `eingangsdatum = stichtag`), Buchung
     *Warenbestand an Inventurdifferenz*.
   - `artikel.bestand` auf `ist_menge` abgleichen.
   Danach `bestandswert_summe` einfrieren (Σ über bereinigte Schichten), `status = Abgeschlossen`,
   `differenz_buchung_id` setzen. Doppel-Abschluss per `offen()`-Guard verhindert.
   **[D6]** Positionen **ohne** `ist_menge` werden NICHT still als 0-Differenz gebucht — sie bleiben
   unverändert und werden im Abschluss-Report transparent als „nicht gezählt: N" ausgewiesen (analog zum
   QDVS-Skip-Prinzip „nie stumm überspringen").

## Eintrittspunkte (gegen „Feature ohne Caller")

- Neues `App\Livewire\Accounting\Inventur` (Route `inventur`, Finanz-Nav, Gate `admin`/`buchhaltung`):
  Inventuren-Liste, Start (Abteilung wählen), Zählliste erfassen, Abschluss mit Differenz-Vorschau +
  Buchungswirkung, danach read-only Protokoll mit Bestandswert + Buchungs-Link.
- Warenwirtschafts-View: zusätzlich **FIFO-Bestandswert** je Artikel + Σ; Schichten aufklappbar
  (Eingänge mit Restmenge, optional Charge/MHD).

## Tests / Verifikation

- **FIFO-Kern**: Eingang 10 @ 2 € + 10 @ 3 €, Verbrauch 15 → Aufwand 35 €, Rest 5 @ 3 € (=15 €), zwei
  Schichtabgänge.
- **Unterdeckung**: Verbrauch > Bestand → `InvalidArgumentException` (Regressionsfix des stillen Clamps).
- **Inventur Schwund**: soll 15 / ist 12 → 3 FIFO ab, *Inventurdifferenz an Warenbestand* 9 €, bestand 12.
- **Inventur Mehrbestand**: soll 12 / ist 14 → neue Schicht 2 @ Schnitt, *Warenbestand an Inventurdifferenz*.
- **Abschluss**: friert Bestandswert, setzt Status, verhindert Doppel-Abschluss.
- **Nicht gezählt**: Position ohne `ist_menge` wird nicht als 0 gebucht, erscheint im Report-Zähler.
- **Regression**: bestehende `Wareneingang`-/`Warenverbrauch`-Tests auf FIFO angepasst.
- **Tenant-Scope/IDOR**: Inventur/Schicht/Position tenant-gescopt.
- **Gates**: Pest grün, PHPStan `-d memory_limit=1G` 0, Pint clean, `migrate:fresh --seed` (Demo:
  ein paar Schichten + eine abgeschlossene Inventur mit Differenz).

## So docken die Folgeschritte an

- **#2 Pflegehilfsmittel (§ 40 SGB XI)**: `resident_id` am `Schichtabgang`/`Warenverbrauch` ⇒ bewerteter,
  bewohnerbezogener Verbrauch fällt direkt aus den FIFO-Kosten.
- **#3 Beschaffung**: Wareneingang gegen Bestellung füllt die Schicht (Einstandspreis aus Bestellposition).
- **#5 Charge/MHD (Art. 18 VO 178/2002)**: `charge_nr`/`mhd` vorhanden ⇒ FEFO-Reihenfolge + Rückruf über
  `Schichtabgang`.
- **#1 Gefahrstoff (GefStoffV § 6)**: Flag + SDB am `Artikel`, unabhängig von diesem Fundament.

## Risiken & Trade-offs

- **Migrations-Altbestand**: vorhandene Demo-Artikel haben Bestand ohne Schichten. Seeder neu aufsetzen
  (`migrate:fresh --seed`) statt Daten-Migration — kein Produktivbestand vorhanden.
- **FIFO-Arithmetik-Rundung**: `einstandspreis` decimal:4, Buchungsbeträge auf 2 gerundet; Tests prüfen
  exakte Summen.
- **Inventur-Differenz-Konvention**: Schwund zehrt FIFO die ältesten Schichten (wie Verbrauch);
  Mehrbestand legt eine Schicht zum Positions-Schnitt an — dokumentiert, nicht geraten.
