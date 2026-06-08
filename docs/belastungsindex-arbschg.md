# Belastungs-Live-Index (§ 5 Abs. 3 Nr. 6 ArbSchG, live)

Macht den GBU-Faktor **„psychische Belastung" datengetrieben und laufend**: aus vorhandenen Signalen einen
**Belastungsindex je Wohnbereich** berechnen, bei Schwellen-Überschreitung **die Leitung benachrichtigen** und per
Klick eine **Entlastungsmaßnahme** an der [Gefährdungsbeurteilung](arbeitsschutz-gefaehrdungsbeurteilung.md) anlegen.

> **Mode A — schicht-/wohnbereichsbezogen, KEIN Personen-Scoring.** Bewusst keine individuelle
> Leistungsbewertung: das wäre Leistungs-/Verhaltenskontrolle (§ 87 Abs. 1 Nr. 6 BetrVG mitbestimmungspflichtig +
> DSFA). Personen erscheinen nur als „Besetzung der betroffenen Schicht". Damit bleibt es § 5-ArbSchG-Bewertung der
> **Arbeitsbedingungen**, nicht der Personen — und es ist sofort ohne Betriebsvereinbarung einsetzbar.

## Norm-Anker

- **§ 5 Abs. 1 + Abs. 3 Nr. 6 ArbSchG** — Beurteilung der Arbeitsbedingungen inkl. psychischer Belastung; hier als
  laufendes Abbild statt einmal-jährlicher Momentaufnahme.
- **§ 3 Abs. 1 / § 4 ArbSchG** — Maßnahmen treffen + anpassen (TOP); der „Entlasten"-Klick erzeugt eine
  dokumentierte `Schutzmassnahme`.
- **§ 618 BGB** — Fürsorgepflicht des Arbeitgebers.
- Abgrenzung [Energiebarometer](bundesland-buchung-energie): dort anonyme Selbstauskunft — hier objektive
  Arbeitsbedingungs-Signale, ebenfalls ohne Personen-Score.

## Signale & Granularität (ehrlich)

- **Pflegelast je Wohnbereich = echt:** eingeschätzte `RiskItem` der Bewohner der Station (über SIS → Bewohner →
  Zimmer → Station) + Pflegegrad-Mix.
- **Personaldeckung = mandantenweit:** § 113c-Soll/Ist (`Betreuungsschluessel`) + `SpitzenzeitAnalyzer` +
  `ScheduleQualityAnalyzer` rechnen heute tenant-weit (Personal ist nicht an Stationen gebunden) → fließen als
  gemeinsamer Druckfaktor ein. Erweiterbar zu echter Wohnbereichs-Deckung, sobald Stations-Dienstpläne existieren.

## Berechnung

`BelastungsAnalyzer` bildet je belegter Station vier geclampte Teil-Scores (0–100): Pflegelast, Deckung,
Spitzenzeit, Ergonomie → gewichtete Summe (`BelastungsKonfig`, editierbar auf der Arbeitsrecht-Seite) →
**Belastungsstufe** `gering/erhöht/hoch/kritisch`. Stufen `hoch`/`kritisch` sind meldepflichtig.

## Workflow

1. **Belastungs-Panel im Dienstplan** zeigt je Wohnbereich Ampel + Score + Signal-Aufschlüsselung (eine Quelle
   `berechneBelastung()` → Anzeige == gemeldete Stufe).
2. **„Leitung melden"** legt eine `Belastungsmeldung` an (Dedupe: nur eine offene je Station) + benachrichtigt
   `admin`/`super-admin` (`BelastungKritisch`).
3. **„Entlasten"** legt an der psychische-Belastung-Gefährdung der gewählten GBU eine `Schutzmassnahme` an und
   verknüpft sie mit der Meldung (Signal → dokumentierte Maßnahme).
4. **Quittieren** auf dem GBU-Screen schließt die Meldung (Leitungs-Workflow + § 6-Doku).

## Modell

- `Belastungsstufe` (Enum), `BelastungsKonfig` (Gewichte/Schwellen je Tenant), `BelastungsBefund` (DTO, kein
  Personenbezug), `BelastungsAnalyzer`, `Belastungsmeldung` (persistierte Überschreitung + Workflow),
  `BelastungMelden`, `EntlastungErgreifen`, Notification `BelastungKritisch`.

## Spätere Stufe (bewusst nicht gebaut)

Mode C (individuelle Auto-Meldung je MA) ist § 87 BetrVG-/DSFA-pflichtig → nur hinter einem
Betriebsvereinbarungs-Schalter denkbar. Nicht Teil dieser Iteration.

## Spec

`docs/superpowers/specs/2026-06-08-belastungsindex-arbschg-design.md`
