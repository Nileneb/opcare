# HACCP-Eigenkontrolle Küche — Temperatur-CCP-Überwachung

Modul in `app/Domains/Catering`. Macht die HACCP-Pflicht-Eigenkontrolle der Heimküche an ihrem kritischsten
Punkt operativ: die **Temperaturüberwachung** der kritischen Kontrollpunkte (CCPs). Route `/haccp`.

## Norm-Anker

- **VO (EG) 852/2004 Art. 5:** Lebensmittelunternehmer richten ein auf den **HACCP-Grundsätzen** beruhendes
  Eigenkontrollverfahren ein und dokumentieren es (CCP, Grenzwerte, Überwachung, Korrekturmaßnahmen,
  Verifizierung, Aufzeichnung).
- **LMHV §§ 3/4:** betriebseigene Maßnahmen + Schulung.
- **Grenzwerte (DIN 10508 / Leitlinien):** Kühlung ≤ **7 °C**, Tiefkühlung ≤ **−18 °C**, Heißhaltung/Warmausgabe
  ≥ **65 °C**.

> **Ehrlich:** opcare bildet das laufende **Eigenkontroll-Journal für den CCP „Temperatur"** ab (Messpunkte,
> tägliche Messwerte, Abweichungen, Korrekturmaßnahmen). Das vollständige HACCP-Konzept (Gefahrenanalyse, Schulung)
> bleibt Aufgabe der Küchenleitung.

## Modell

- **`HaccpArt`** (Enum) — Kühlung/Tiefkühlung/Heißhaltung/Ausgabe; je Default-Grenzwert + Richtung (`istMax()`:
  Kühlen = Wert muss ≤ Grenzwert; Heißhalten = Wert muss ≥ Grenzwert).
- **`HaccpMesspunkt`** — ein CCP (z. B. „Kühlhaus Gemüse", „Bain-Marie Ausgabe"): `art`, `grenzwert` (Default aus
  art, überschreibbar), `aktiv`. `istAbweichung(float)` (Grenzfall exakt am Grenzwert = OK), `offeneAbweichung()`.
- **`Temperaturmessung`** — `gemessen_am`, `wert`, `abweichung` (abgeleitet), `korrekturmassnahme`, `erfasst_von`.
  `offen()` = abweichung ohne Korrekturmaßnahme.

## Workflow

1. **Messpunkte** anlegen (Art → Default-Grenzwert).
2. **Tagesblatt:** je Messpunkt heutige Messung erfassen (Wert °C, Zeitpunkt — nicht in der Zukunft) → `abweichung`
   wird automatisch gegen den Grenzwert gesetzt.
3. **Bei Abweichung:** roter Kasten (Soll/Ist) bleibt offen, bis eine **Korrekturmaßnahme** dokumentiert ist
   (single source of truth `offen()`/`offeneAbweichung()`).

## Spec

`docs/superpowers/specs/2026-06-08-haccp-kueche-design.md`

## Naheliegender Fast-Follow

**Reinigungs-/Desinfektionsplan** (Aufgaben mit Intervall + Erledigungs-Nachweis, Frist-Ampel) als zweite
HACCP-Eigenkontroll-Säule neben der Temperatur — reuse vom Wartungsplan-/Nachweis-mit-Frist-Muster.
