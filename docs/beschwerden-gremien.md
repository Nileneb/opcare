# Beschwerde-/Gewaltschutz, Gremien/Heimbeirat & Betriebsarzt/Sifa

Drei eng verwandte Heimrecht-/Mitwirkungs- und Arbeitsschutz-Module, alle operativ (nicht nur darstellbar) und
mandantengetrennt, revisionssicher protokolliert.

## 1. Beschwerde- & Gewaltschutz-Management (`/qualitaet/beschwerden`)

Erfassen, bearbeiten und an die betroffene Abteilung **weiterleiten** — der Kern ist die Delegation mit
Anonymitätswahl des Melders.

### Rechtsgrundlagen (per Legal Data Hunter belegt)
- **§ 113 SGB XI** — Maßstäbe und Grundsätze der Qualität; Beschwerdemanagement ist verpflichtender QM-Bestandteil.
- **Landes-WTG / WBVG** — Beschwerderecht der Bewohner:innen (z. B. WTG NRW, BremWoBeG, BbgPBWoG).
- **§ 5 SGB XI / Gewaltprävention** — Gewaltvorfälle als eigene Kategorie mit Sofortmaßnahmen-Pflicht.

### Delegation / Weiterleitung (anonym oder namentlich)
- Beim Eingang wählt der **Melder die Sichtbarkeit**: `namentlich` oder `anonym`.
  - Bei `anonym` wird **keine** Identität gespeichert (Datensparsamkeit, nicht bloßes Ausblenden) und `melderAnzeige()`
    liefert immer „anonym".
- Das QM (Rolle `admin`/`pflegefachkraft`) leitet eine Beschwerde an einen Bereich weiter (z. B. QM erhält Feedback
  zur Küche → leitet an die **Küche** weiter). Pro Weiterleitung gibt es ein `anonym`-Flag:
  - Hat der Melder `anonym` gewählt, wird die Weiterleitung **erzwungen anonym** — die Wahl ist nicht aufhebbar.
  - Hat der Melder `namentlich` gewählt, kann das QM optional dennoch anonym weiterleiten.
- Die Weiterleitung benachrichtigt **alle Inhaber:innen der Bereichsrolle** in-app (`database`+`broadcast`); die
  Benachrichtigung enthält die Melder-Identität nur, wenn nicht anonym.
- Die Bereichsrolle sieht „ihre" weitergeleiteten Beschwerden und kann **Stellungnahme/Maßnahme** beisteuern.

### Ampel & Workflow
- Status: `eingegangen → in_bearbeitung → weitergeleitet → erledigt|abgelehnt`.
- Ampel: Gewaltvorfall ohne Sofortmaßnahme **rot**, Frist überschritten **rot**, Frist ≤ 7 Tage **amber**,
  offener Gewaltvorfall **amber**, erledigt/abgelehnt **grau** (Lob **grün**).
- Jeder Schritt (Weiterleitung/Stellungnahme/Maßnahme/Statuswechsel) landet append-only im Verlauf.

## 2. Gremien & Heimbeirat (`/qualitaet/gremien`)

Mitwirkungs- und Selbstverwaltungsgremien mit Wahlperioden- und Sitzungs-Ampel.

### Rechtsgrundlagen
- **HeimmwV** (Verordnung über die Mitwirkung der Bewohner) + **§ 10 WBVG** + Landes-WTG — Heimbeirat/
  Bewohnervertretung, Wahl, Amtszeit (HeimmwV § 8: zwei Jahre).
- **§ 113 SGB XI** — Qualitätszirkel.
- **§ 11 ASiG** — Arbeitsschutzausschuss (ASA), mindestens vierteljährliche Sitzung.

### Funktion
- Typ steuert Standardwerte: Heimbeirat → Wahlperiode 24 Monate; ASA → Sitzungstakt 3 Monate.
- **Mitglieder** mit Funktion (Vorsitz/Stellvertretung/Schriftführung/Mitglied) und Art (Bewohner/Angehörige/
  Mitarbeiter/Leitung/extern/Betriebsarzt/Sifa) — verknüpfbar mit Benutzer- oder Bewohner-Stammdaten.
- **Sitzungen** mit Protokoll, Beschlüssen und Teilnehmerzahl.
- Ampel: Wahlperiode abgelaufen **rot**, Sitzungstakt überschritten **amber**, sonst **grün**, aufgelöst **grau**.

## 3. Betriebsarzt & Fachkraft für Arbeitssicherheit (Sifa)

Stammdaten als zweite Sektion auf `/arbeitsschutz/nachweise`.

### Rechtsgrundlagen
- **ASiG §§ 2/5/6** — Bestellung von Betriebsarzt und Sifa (ab 1 Beschäftigtem).
- **DGUV V2** — Einsatzzeiten und regelmäßige Betriebsbegehungen.
- Speist den Arbeitsschutzausschuss (§ 11 ASiG) als Gremium (siehe oben).

### Funktion
- Art (Betriebsarzt/Sifa), intern/extern, Firma/Dienst, Kontakt, jährliche Einsatzzeit, Bestelldatum/Vertrag.
- **Begehungs-Ampel** (Nachweis-mit-Frist): letzte Begehung + Intervall → nächste fällig; überfällig/Vertrag
  abgelaufen **rot**, ≤ 30 Tage oder noch keine Begehung bei Pflichtintervall **amber**, sonst **grün**.

## Datenmodell

| Tabelle | Domain | Zweck |
| --- | --- | --- |
| `beschwerden` | Quality | Eingang + Status + Melder-Sichtbarkeit + Gewaltschutz-Sofortmaßnahme |
| `beschwerde_vorgaenge` | Quality | append-only Verlauf inkl. Weiterleitung (`an_bereich`, `anonym`) |
| `gremien` | Quality | Heimbeirat/ASA/Qualitätszirkel mit Wahl-/Sitzungstakt |
| `gremium_mitglieder` | Quality | Mitglieder mit Funktion/Art |
| `gremium_sitzungen` | Quality | Sitzungsprotokolle + Beschlüsse |
| `betriebsbetreuungen` | Personnel | Betriebsarzt/Sifa-Stammdaten + Begehungsfrist |

## Berechtigungen
- **Erfassen** einer Beschwerde: alle operativen Rollen (außer reines Leserecht).
- **Verwalten** (bearbeiten/weiterleiten/erledigen) + Gremien + Betriebsbetreuung: `admin`/`pflegefachkraft` (QM/Leitung).
- **Stellungnahme** zu einer weitergeleiteten Beschwerde: die Inhaber:innen der adressierten Bereichsrolle.
- Jede schreibende Aktion ist server-seitig (`abort_unless`) abgesichert, strikt mandantengetrennt, `LogsActivity`.

## Tests
`tests/Feature/Quality/BeschwerdeModelTest.php`, `BeschwerdenTest.php`, `GremiumModelTest.php`, `GremienTest.php`,
`tests/Feature/Personnel/BetriebsbetreuungTest.php` — Ampel-Logik, Anonymitäts-Bindung bei der Weiterleitung,
Benachrichtigung der Bereichsrolle, Gewaltvorfall-Pflichtfeld, Berechtigungs-Guards (24 neue Tests).
