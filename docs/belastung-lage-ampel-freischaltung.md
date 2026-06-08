# Lage-Ampel (0-10 Farbverlauf) + Beschluss-Freischaltung (Mode B/C)

Erweitert den [Belastungs-Live-Index](belastungsindex-arbschg.md) um eine weichere Anzeige und um eine
**individuelle, freiwillige** Selbst-Ampel, die erst nach einem **Mitarbeitenden-Beschluss** freigeschaltet wird.

## Teil A — Lage-Ampel als Farbverlauf

Statt vier starrer Stufen zeigt die Belastungs-Anzeige eine **0-10-Lage-Skala rein farblich** (keine Zahl):
**10 = grün = gut**, 0 = rot = kritisch (invertiert zur Roh-Belastung, `lage = round((100 − score)/10)`). Die Farben
laufen ineinander über (HSL-Hue-Interpolation: 0-2 rot, ~4-5 gelb, 7-10 grün). Die **Meldepflicht** bleibt intern am
Score. Gilt im Dienstplan-Belastungs-Panel und bei den GBU-Belastungsmeldungen.
Helper: `App\Domains\Arbeitsschutz\Support\BelastungsAmpel::lageAusScore()` / `::farbe()`.

## Teil B — Beschluss-Freischaltung + Selbst-Ampel + Selbst-Meldung (Mode B/C)

> **BetrVG-Weg in der App:** Eine individuelle Belastungsampel berührt § 87 Abs. 1 Nr. 6 BetrVG. Statt eines toten
> Schalters wird sie durch einen **Beschluss der Mitarbeitenden** (Voting-Modul) scharfgeschaltet — ein echter,
> dokumentierter Mitbestimmungs-Nachweis.

- **`BelastungFreischaltung`** — aktiv, sobald ein angenommener Mitarbeitenden-**Beschluss** sie freischaltet
  (`BelastungFreischalten::ausBeschluss` prüft Art/Elektorat/Status **und echte Mehrheit** der Zustimmungs-Option).
  Rücknehmbar. `aktivFuer(tenantId)` = die eine Quelle, ob Mode B/C scharf sind. Verwaltung auf der Arbeitsrecht-Seite.
- **Individuelle Selbst-Ampel (Mode B):** `PersoenlicheBelastung` (0-10, Selbst-Slider wie das Energiebarometer,
  **kein Activity-Log, kein fremder Einblick** — jede:r sieht nur den eigenen Wert). Auf dem Energiebarometer-Screen,
  nur sichtbar bei aktiver Freischaltung.
- **Selbst-Meldung (Mode C, ausschließlich selbst-initiiert):** „Überlastung an Leitung melden" erzeugt eine
  `SelbstmeldungUeberlastung` (named, weil selbst ausgelöst) + Benachrichtigung an die Leitung; Quittieren auf dem
  GBU-Screen. **Keine automatische Personen-Überwachung.**

## Datenschutz

- Selbst-Ampel + Meldung nur bei aktiver Freischaltung (Service erzwingt 403). Vorgesetzte sehen nur die selbst
  gemeldete Überlastung, **nie** den Roh-Slider-Verlauf (strukturell: es existiert keine Query darauf;
  Regressionstest sichert die Invariante).
- Echtes Auto-Scoring je Person bleibt ungebaut (bräuchte zusätzlich DSFA).

## Spec

`docs/superpowers/specs/2026-06-08-belastung-lage-ampel-freischaltung-design.md`
