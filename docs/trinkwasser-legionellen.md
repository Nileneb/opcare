# Trinkwasser / Legionellen-Überwachung (TrinkwV 2023)

Modul in `app/Domains/Facility` (Haustechnik). Macht die gesetzliche Legionellen-Untersuchungspflicht für
Trinkwasser-Großanlagen im Heim operativ. Route `/trinkwasser`.

## Norm-Anker (aktuellste Fassung: TrinkwV 2023, in Kraft seit 24.06.2023)

- **Untersuchungspflicht** (§ 31 TrinkwV 2023; vormals § 14 Abs. 3 TrinkwV 2001): **Großanlagen** zur
  Trinkwassererwärmung (Speicher > 400 l ODER > 3 l Leitungsinhalt zwischen Erwärmer und Entnahme) **mit
  Vernebelung** (Duschen) sind auf **Legionellen** zu untersuchen.
- **Intervall:** Gebäude mit öffentlicher/gewerblicher Tätigkeit (Pflegeheim) → **jährlich**.
- **Technischer Maßnahmenwert:** **100 KbE / 100 ml** Legionellen (Anlage 3 Teil II TrinkwV 2023).
- **Bei Überschreitung** (§ 51): unverzüglich Ursachenuntersuchung + Gefährdungsanalyse + Maßnahmen, **Anzeige
  an das Gesundheitsamt**.
- **Probenahmestellen:** repräsentativ — Austritt Trinkwassererwärmer + entferntester Punkt je Steigstrang.

> **Ehrlich:** opcare bildet die **Dokumentations- und Fristenpflicht** ab (wer hat wann wo gemessen, Wert,
> nächste Frist, Überschreitungs-Workflow). Die akkreditierte Probennahme/Analytik selbst macht ein zugelassenes
> Labor — das ersetzt opcare nicht.

## Modell

- **`Trinkwasseranlage`** — Großanlage je Gebäude/Strang: Intervall (Default 12 Monate), `letzte_untersuchung_am`;
  Frist-Ampel `faelligkeitsStatus()` → rot (überfällig/nie), gelb (< 30 Tage), grün.
- **`Probenahmestelle`** — repräsentative Messpunkte je Anlage.
- **`Legionellenbefund`** — Untersuchung je Stelle: `untersucht_am`, `labor`, `kbe_pro_100ml`,
  `ueberschreitung` (= `kbe >= 100`), `massnahme`, `gesundheitsamt_gemeldet_am`. Konstante `MASSNAHMENWERT = 100`.

## Workflow

1. Anlage(n) + Probenahmestellen anlegen (Haustechnik).
2. **Befund erfassen** (Datum, Stelle, KbE-Wert, Labor) → `ueberschreitung` wird automatisch gesetzt,
   `letzte_untersuchung_am` fortgeschrieben (max der Befunddaten — ein nachgetragener älterer Befund setzt die
   Frist nicht zurück).
3. **Bei Überschreitung** (≥ 100 KbE/100 ml): prominenter roter **§ 51-Pflicht-Kasten** bleibt offen
   (`offeneUeberschreitung()`), bis Maßnahme **und** Gesundheitsamt-Meldung gesetzt sind — die Pflicht wird
   sichtbar gemacht, nicht weggeklickt.
4. **Frist-Ampel:** überfällige jährliche Untersuchung erscheint rot (wie der Wartungsplan der Haustechnik).

## Spec

`docs/superpowers/specs/2026-06-08-trinkwasser-legionellen-design.md`
