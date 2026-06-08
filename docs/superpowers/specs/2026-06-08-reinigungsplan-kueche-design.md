# Reinigungs- und Desinfektionsplan Küche — Design

**Goal:** Die zweite HACCP-Eigenkontroll-Säule neben der Temperatur (CCP) operativ machen: den
**Reinigungs- und Desinfektionsplan** der Küche — Aufgaben mit Intervall, Erledigungs-Nachweis und
Fälligkeits-Ampel (überfällige Reinigung sichtbar).

## Norm-Anker

- **VO (EG) 852/2004 Anhang II** (Kap. I/II/V): Betriebsstätten, Ausrüstung und Gegenstände sind **sauber und
  instand zu halten**; Reinigung/Desinfektion nach Bedarf in angemessenen Abständen.
- **LMHV §§ 3/4:** betriebseigene Maßnahmen (dokumentierter Reinigungsplan „Was — Womit — Wie — Wann — Wer").
- Ergänzt das HACCP-Temperatur-Journal ([[opcare-haccp-kueche]]) zur vollständigeren Eigenkontroll-Doku.

> Ehrlich: opcare führt den **Reinigungsplan + Erledigungs-Nachweis mit Fälligkeits-Ampel**. Die fachgerechte
> Reinigung/Desinfektion selbst (Mittel, Dosierung, Einwirkzeit) bleibt die betriebliche Durchführung.

## Architektur

Teil der **`app/Domains/Catering`**-Domäne (neben HACCP). Reuse der Frist-Ampel von
`Trinkwasseranlage::faelligkeitsStatus()` / Wartungsplan. Zwei Modelle (BaseModel) + ein Enum:

- **Enum `ReinigungsIntervall`** (string-backed, `label()` + `tage(): int`): `Taeglich` (1), `Woechentlich` (7),
  `ZweiWoechentlich` (14), `Monatlich` (30), `Quartalsweise` (90).
- **`Reinigungsaufgabe`** — eine Plan-Position: `bezeichnung` (z. B. „Arbeitsflächen", „Kühlhaus", „Böden",
  „Dunstabzug"), `bereich` (string nullable, z. B. Küche/Lager/Ausgabe), `intervall` (ReinigungsIntervall),
  `verantwortlich` (string nullable), `aktiv` (bool), `letzte_erledigung_am` (date nullable, abgeleitet).
  Helper `naechsteFaelligkeit(): ?Carbon` (letzte + intervall->tage), `istUeberfaellig(): bool` (nie erledigt
  ODER fällig < heute), `faelligkeitsStatus(): string` ('rot'/'gelb'/'gruen'). `nachweise()` HasMany.
- **`Reinigungsnachweis`** — append-only Erledigungs-Beleg: `reinigungsaufgabe_id`, `erledigt_am` (date),
  `erledigt_von` (FK users nullable), `bemerkung` (text nullable).

## Service

- `ReinigungErledigen::handle(Reinigungsaufgabe $a, string $erledigtAm, ?int $userId = null, ?string $bemerkung = null): Reinigungsnachweis`
  — legt `Reinigungsnachweis` an, aktualisiert `aufgabe.letzte_erledigung_am` = max(bisher, erledigtAm).
  **`erledigt_am` nicht in der Zukunft** (UI `before_or_equal:today`).
- `ReinigungsplanMonitor::status(): array` — je aktiver Aufgabe `faelligkeitsStatus()` + nächste Fälligkeit,
  tenant-scoped. (Frist-Status aus EINER Methode — keine divergierende View-Query, vgl. HACCP/Trinkwasser-Lektion.)

## UI

Livewire `app/Livewire/Catering/Reinigungsplan.php` (Route `/reinigungsplan`, Gate admin/pflegefachkraft/kueche,
tenant-scoped): Aufgaben-Liste mit **Fälligkeits-Ampel** (überfällig rot „seit X Tagen", bald gelb, grün) +
„Erledigt"-Button (→ `ReinigungErledigen`, optional Bemerkung); Aufgaben verwalten (anlegen: Bezeichnung,
Bereich, Intervall, Verantwortlich); Nachweis-Historie je Aufgabe. Nav-Eintrag bei Küche/HACCP. Norm-Fußnote.

## Tests

`naechsteFaelligkeit`/`istUeberfaellig`/`faelligkeitsStatus` (nie erledigt → rot; täglich + letzte gestern → fällig;
wöchentlich + letzte vor 8 Tagen → rot; < Intervall → grün). `ReinigungErledigen` legt Nachweis an + aktualisiert
`letzte_erledigung_am` als Max (älterer Nachweis setzt nicht zurück); Zukunftsdatum → Validierungsfehler.
`ReinigungsplanMonitor` tenant-scoped. UI: erledigen schaltet Ampel auf grün; Gate-403; IDOR.
