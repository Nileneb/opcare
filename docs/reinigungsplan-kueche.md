# Reinigungs- und Desinfektionsplan Küche

Zweite HACCP-Eigenkontroll-Säule neben der [Temperatur-CCP-Überwachung](haccp-kueche.md). Modul in
`app/Domains/Catering`. Route `/reinigungsplan`.

## Norm-Anker

- **VO (EG) 852/2004 Anhang II** (Kap. I/II/V): Betriebsstätten, Ausrüstung und Gegenstände sind **sauber und
  instand zu halten**; Reinigung/Desinfektion in angemessenen Abständen.
- **LMHV §§ 3/4:** dokumentierter Reinigungsplan „Was — Womit — Wie — Wann — Wer".

> **Ehrlich:** opcare führt den **Reinigungsplan + Erledigungs-Nachweis mit Fälligkeits-Ampel**. Die fachgerechte
> Reinigung/Desinfektion selbst (Mittel, Dosierung, Einwirkzeit) bleibt die betriebliche Durchführung.

## Modell

- **`ReinigungsIntervall`** (Enum) — Täglich (1) / Wöchentlich (7) / Zweiwöchentlich (14) / Monatlich (30) /
  Vierteljährlich (90 Tage).
- **`Reinigungsaufgabe`** — Plan-Position: `bezeichnung`, `bereich`, `intervall`, `verantwortlich`, `aktiv`,
  `letzte_erledigung_am`. **Fälligkeits-Ampel** `faelligkeitsStatus()` → rot (überfällig/nie), gelb (≤ 3 Tage),
  grün (gespiegelt vom Trinkwasser-/Wartungsplan-Muster).
- **`Reinigungsnachweis`** — append-only Erledigungs-Beleg: `erledigt_am`, `erledigt_von`, `bemerkung`.

## Workflow

1. **Aufgaben** anlegen (Bezeichnung, Bereich, Intervall, Verantwortlich).
2. **Plan-Liste** mit Fälligkeits-Ampel (überfällig rot „seit X Tagen", bald fällig gelb, erledigt grün).
3. **„Erledigt" melden** (Datum default heute — nicht in der Zukunft; optional Bemerkung) → Nachweis +
   `letzte_erledigung_am` als Max (ein nachgetragener älterer Nachweis setzt die Frist nicht zurück).

## Spec

`docs/superpowers/specs/2026-06-08-reinigungsplan-kueche-design.md`
