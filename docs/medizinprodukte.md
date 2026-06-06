# Medizinprodukte: Bestandsverzeichnis & Medizinproduktebuch (MPBetreibV)

Verwaltung aller aktiven, nichtimplantierbaren Medizinprodukte der Einrichtung: Bestandsverzeichnis (§ 14),
Medizinproduktebuch (§ 13) mit STK/MTK-Prüffristen-Ampel (§ 12/§ 15), dokumentierten Einweisungen (§ 4/§ 11)
und Funktionsstörungen/Vorkommnissen (§ 13, BfArM-Meldung). Schließt **Audit-Lücke „Medizinprodukte"** und
ist der Top-Kandidat der [Gesetzes-Recherche](recherche-offene-punkte-2026-06.md) (§ 2).

![Medizinprodukte](screenshots/medizinprodukte.png)

## Rechtsgrundlagen (per Legal Data Hunter belegt)

- **MPBetreibV 2025** — [gesetze-im-internet.de/mpbetreibv_2025](https://www.gesetze-im-internet.de/mpbetreibv_2025/);
  gilt nach **§ 2 Abs. 4** ausdrücklich auch für Pflegeeinrichtungen.
  - **§ 14 Bestandsverzeichnis** — für **alle** aktiven nichtimplantierbaren Produkte: Bezeichnung/Art/Typ,
    Los-/Seriennummer, Anschaffungsjahr, Hersteller/Bevollmächtigter, betriebl. Identifikationsnummer,
    Standort + betriebliche Zuordnung.
  - **§ 13 Medizinproduktebuch** — für Produkte der **Anlagen 1+2**: Funktionsprüfung/Einweisung, eingewiesene
    Personen, Fristen/Datum/Ergebnis von STK/MTK, Funktionsstörungen, Vorkommnismeldungen.
    Aufbewahrung **5 Jahre** nach Außerbetriebnahme.
  - **§ 4 / § 11 Einweisung** — nur eingewiesene Personen dürfen das Produkt benutzen; Einweisung ist zu
    dokumentieren.
  - **§ 12 STK** (sicherheitstechnische Kontrolle, Anlage 1, spätestens alle 2 Jahre) und **§ 15 MTK**
    (messtechnische Kontrolle, Anlage-2-Fristen) → Frist-Ampel.
  - **§ 6 Beauftragte:r für Medizinproduktesicherheit (MPSB)** ab > 20 Beschäftigten — im
    [Beauftragten-Register](../app/Domains/Personnel/Support/BeauftragtenrolleDefaults.php) als Rolle
    `mp_sicherheit` bereits enthalten.
  - **§ 19** — Verstöße sind Ordnungswidrigkeiten.
- **§ 3 MPAMIV** — Meldepflicht schwerwiegender Vorkommnisse an das **BfArM** (im Vorkommnis-Eintrag als
  `bfarm_gemeldet` nachgehalten).

> Quellen ermittelt mit dem Legal-Data-Hunter-MCP (Volltexte aus `DE/BGBl`). Fachliche Prüfung durch eine
> qualifizierte Person bleibt erforderlich; Anlagen-Zuordnung pro Produkt ist im Einzelfall festzulegen.

## Datenmodell (`app/Domains/Facility`)

| Tabelle | Zweck | Kernfelder |
| --- | --- | --- |
| `medizinprodukte` | Bestandsverzeichnis § 14 + Stammdaten Medizinproduktebuch § 13 | `bezeichnung`, `typ`, `hersteller`, `seriennummer`, `udi_di`, `inventarnummer`, `anschaffungsjahr`, `standort`, `zuordnung`, `risikoklasse`, `anlage`, `inbetriebnahme_am`, `letzte_stk`/`stk_intervall_monate`, `letzte_mtk`/`mtk_intervall_monate`, `ausser_betrieb_am` |
| `medizinprodukt_einweisungen` | eingewiesene Personen (§ 4/§ 11) | `user_id`, `eingewiesen_am`, `eingewiesen_durch`, `art` (erst/folge) |
| `medizinprodukt_vorkommnisse` | Funktionsstörungen/Vorkommnisse (§ 13) | `datum`, `art`, `beschreibung`, `massnahme`, `bfarm_gemeldet`, `gemeldet_von`, `behoben_am` |

- **`MpAnlage`** (`keine`/`anlage1`/`anlage2`) treibt die Pflichten: `brauchtMedizinproduktebuch()`,
  `brauchtStk()` (Anlage 1, Standardintervall 24 Monate), `brauchtMtk()` (Anlage 2).
- **`MpVorkommnisArt`** (`funktionsstoerung`/`beinahe_vorkommnis`/`vorkommnis`) — `meldepflichtig()` markiert
  schwerwiegende Vorkommnisse (BfArM).
- **Prüf-Ampel** (`Medizinprodukt::pruefAmpel()`): `grau` (keine Anlagen-Pflicht), `green`, `amber`
  (Frist ≤ 30 Tage **oder** pflichtige Kontrolle ohne dokumentierten Termin), `red` (überfällig) — dasselbe
  *Nachweis-mit-Frist*-Muster wie bei der [Haustechnik](../app/Domains/Facility/Models/FacilityAsset.php) und
  dem Beauftragten-Register.

## Bedienung (`/medizinprodukte`)

- **Bestandsverzeichnis** als Liste mit Prüf-Ampel; Klick wählt ein Produkt für die Detailansicht.
- **Aufnehmen**: § 14-Stammdaten; bei Anlage 1 wird das STK-Standardintervall (24 Monate) vorbelegt.
- **Detailansicht**: Stammdaten, Prüffristen-Tabelle (STK/MTK dokumentieren), eingewiesene Personen,
  Funktionsstörungen/Vorkommnisse (behoben markieren, BfArM-Meldung quittieren),
  Außer-/Wieder-Inbetriebnahme.
- **Berechtigung**: Verwalten dürfen `admin`, `pflegefachkraft`, `haustechnik` (sonst nur Ansicht); jede
  schreibende Aktion ist server-seitig per `abort_unless` abgesichert. Alle Änderungen werden über
  `LogsActivity` revisionssicher protokolliert; alles strikt tenant-scoped (`BelongsToTenant`).

## Tests

- `tests/Feature/Facility/MedizinproduktModelTest.php` — Ampel-Logik (STK überfällig → rot, Anlage „keine" →
  grau, Anlage 2 ohne STK, pflichtige Kontrolle ohne Termin → amber, Außerbetriebnahme).
- `tests/Feature/Facility/MedizinprodukteTest.php` — Anlegen (+ STK-Intervall), STK dokumentieren, Einweisung,
  Vorkommnis erfassen, Berechtigungs-Guard.
