# Trinkwasser / Legionellen-Überwachung (TrinkwV 2023) — Design

**Goal:** Die gesetzliche Legionellen-Untersuchungspflicht für Trinkwasser-Großanlagen im Heim operativ machen —
Probenahmestellen-Register, Untersuchungs-Frist-Ampel (jährlich), Befund-Erfassung gegen den technischen
Maßnahmenwert, und der Pflicht-Workflow bei Überschreitung (Maßnahmen + Gesundheitsamt-Anzeige).

## Norm-Anker (aktuellste Fassung: TrinkwV 2023, in Kraft 24.06.2023)

- **Untersuchungspflicht** (§ 31 TrinkwV 2023, vormals § 14 Abs. 3 TrinkwV 2001): **Großanlagen** zur
  Trinkwassererwärmung (Speicher > 400 l ODER > 3 l Leitungsinhalt zw. Erwärmer und Entnahme) **mit Vernebelung**
  (Duschen) sind auf **Legionellen** zu untersuchen.
- **Intervall:** Gebäude mit **öffentlicher/gewerblicher Tätigkeit** (Pflegeheim) → **jährlich** (§ 31 Abs. 2;
  reine vermietete Wohngebäude wären 3-jährlich — hier NICHT der Fall).
- **Technischer Maßnahmenwert:** **100 KbE / 100 ml** Legionellen (Anlage 3 Teil II TrinkwV 2023).
- **Bei Überschreitung** (§ 51 TrinkwV 2023): unverzüglich Ursachenuntersuchung + Gefährdungsanalyse +
  Maßnahmen; **Anzeige an das Gesundheitsamt** (§ 13/§ 51).
- **Probenahmestellen:** repräsentativ — Austritt Trinkwassererwärmer + entferntester Punkt je Steigstrang.

> Ehrlich: opcare bildet die **Dokumentations- und Fristenpflicht** ab (wer hat wann wo gemessen, Wert, Frist,
> Überschreitungs-Workflow). Die akkreditierte Probennahme/Analytik selbst macht ein zugelassenes Labor.

## Architektur

Neuer Teil der bestehenden **`app/Domains/Facility`**-Domäne (Haustechnik verwaltet die Trinkwasser-Installation;
das Frist-Ampel-Muster existiert dort bereits bei `Medizinprodukt` STK/MTK). Drei Modelle (alle `BaseModel`):

- **`Trinkwasseranlage`** — eine Großanlage je Gebäude/Strang: `bezeichnung`, `gebaeude` (Freitext/Asset-Bezug),
  `ist_grossanlage` (bool, Untersuchungspflicht), `untersuchungsintervall_monate` (default **12**),
  `letzte_untersuchung_am` (date, abgeleitet), `notiz`. Relation `probenahmestellen()`, `befunde()`.
  Helper `naechsteFaelligkeit(): ?Carbon` (letzte + Intervall), `istUeberfaellig(): bool` (Frist-Ampel).
- **`Probenahmestelle`** — `trinkwasseranlage_id`, `bezeichnung`, `ort` (z. B. „Austritt Erwärmer",
  „entferntester Punkt Steigstrang 3").
- **`Legionellenbefund`** — eine Untersuchung je Probenahmestelle: `trinkwasseranlage_id`, `probenahmestelle_id`,
  `untersucht_am` (date), `labor` (string nullable), `kbe_pro_100ml` (unsigned int), `ueberschreitung` (bool,
  abgeleitet `kbe >= 100`), `massnahme` (text nullable), `gesundheitsamt_gemeldet_am` (date nullable — § 51 Anzeige).
  Konstante `MASSNAHMENWERT = 100`.

**Frist-Ampel:** wie der Wartungsplan — überfällige jährliche Untersuchung = rot; in <30 Tagen = gelb. Keine
Inbetriebnahme-Schalter nötig (reine Doku-/Fristen-Funktion).

## Service / Logik

- `BefundErfassen::handle(...)`: legt `Legionellenbefund` an, setzt `ueberschreitung = kbe >= 100`, aktualisiert
  `anlage.letzte_untersuchung_am` (max der Befunddaten). Bei Überschreitung KEIN stilles Schlucken — die UI zeigt
  den Pflicht-Workflow (Maßnahme + Gesundheitsamt-Anzeige offen → rot, bis `gesundheitsamt_gemeldet_am` + Maßnahme gesetzt).
- `LegionellenMonitor`: je Anlage Status (grün/gelb/rot Frist) + offene Überschreitungs-Workflows.

## UI

Livewire `app/Livewire/Facility/Trinkwasser.php` (Route `/trinkwasser`, Gate admin/haustechnik, tenant-scoped):
Anlagen-Liste mit Frist-Ampel; Probenahmestellen verwalten; Befund erfassen (Stelle, Datum, KbE, Labor) →
bei ≥ 100 prominenter **Maßnahmen-/Meldepflicht-Kasten** (§ 51); Befund-Historie je Anlage. Nav-Eintrag bei Haustechnik.

## Tests

Modelle/Frist (`istUeberfaellig`, `naechsteFaelligkeit`); `BefundErfassen` setzt `ueberschreitung` korrekt bei
99/100/101 KbE; Überschreitung erzeugt offenen Workflow bis Meldung+Maßnahme; Frist-Ampel rot bei > 12 Monaten;
Tenant-Scope/IDOR; kein stilles Schlucken.
