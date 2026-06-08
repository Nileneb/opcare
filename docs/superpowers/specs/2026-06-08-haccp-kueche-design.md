# HACCP-Eigenkontrolle Küche — Temperatur-CCP-Überwachung — Design

**Goal:** Die HACCP-Pflicht-Eigenkontrolle der Heimküche an ihrem kritischsten Punkt operativ machen: die
**Temperaturüberwachung** der kritischen Kontrollpunkte (Kühlung/Tiefkühlung/Heißhaltung) — tägliche Messung
gegen den Grenzwert, mit Abweichungs- und Korrekturmaßnahmen-Workflow.

## Norm-Anker

- **VO (EG) 852/2004 Art. 5:** Lebensmittelunternehmer müssen ein auf den **HACCP-Grundsätzen** beruhendes
  Eigenkontrollverfahren einrichten und dokumentieren (Gefahrenanalyse, kritische Kontrollpunkte/CCP, Grenzwerte,
  Überwachung, Korrekturmaßnahmen, Verifizierung, Dokumentation).
- **LMHV (Lebensmittelhygiene-Verordnung) §§ 3/4:** betriebseigene Maßnahmen + Schulung.
- **Temperatur-Grenzwerte (DIN 10508 / Leitlinien):** Kühlung ≤ **7 °C** (produktspezifisch teils kälter),
  Tiefkühlung ≤ **−18 °C**, Heißhaltung/Warmausgabe ≥ **65 °C**.

> Ehrlich: opcare bildet die **Dokumentations-/Überwachungspflicht** ab (CCP-Messpunkte, tägliche Messwerte,
> Abweichungen, Korrekturmaßnahmen). Das vollständige betriebliche HACCP-Konzept (Gefahrenanalyse, Schulung) bleibt
> Aufgabe der Küchenleitung; dieses Modul ist das laufende Eigenkontroll-Journal für den CCP „Temperatur".

## Architektur

Teil der **`app/Domains/Catering`**-Domäne. Zwei Modelle (BaseModel) + ein Enum:

- **Enum `HaccpArt`** (string-backed, `label()` + `grenzwertDefault(): float` + `istMax(): bool`):
  `Kuehlung` (7.0, max), `Tiefkuehlung` (−18.0, max), `Heisshaltung` (65.0, min), `Ausgabe` (65.0, min).
  „max" = Messwert muss **≤** Grenzwert (Kühlen: zu warm = Abweichung); „min" = Messwert muss **≥** Grenzwert
  (Heißhalten: zu kalt = Abweichung).
- **`HaccpMesspunkt`** — ein kritischer Kontrollpunkt: `bezeichnung` (z. B. „Kühlhaus Gemüse", „Bain-Marie
  Ausgabe"), `art` (HaccpArt), `grenzwert` (decimal, Default aus art, überschreibbar), `aktiv` (bool).
  Helper `istAbweichung(float $wert): bool` (anhand art-Richtung), `messungen()` HasMany.
- **`Temperaturmessung`** — `haccp_messpunkt_id`, `gemessen_am` (datetime), `wert` (decimal °C),
  `abweichung` (bool, abgeleitet bei Erfassung), `korrekturmassnahme` (text nullable), `erfasst_von` (FK users).
  Helper `offen(): bool` = `abweichung && korrekturmassnahme === null`.

## Service

- `MessungErfassen::handle(HaccpMesspunkt $mp, float $wert, string $gemessenAm, ?int $userId, ?string $korrektur = null): Temperaturmessung`
  — setzt `abweichung = $mp->istAbweichung($wert)`. **`gemessen_am` darf nicht in der Zukunft liegen**
  (Validierung in der UI `before_or_equal`, Service nimmt den Wert).
- `HaccpMonitor::tagesblatt(?string $datum): array` — je aktivem Messpunkt die Messung(en) des Tages +
  `offeneAbweichung` (Messpunkt hat eine Messung mit `offen()===true`).

## UI

Livewire `app/Livewire/Catering/Haccp.php` (Route `/haccp`, Gate admin/pflegefachkraft/kueche, tenant-scoped):
Messpunkte verwalten (anlegen, art→Default-Grenzwert); **Tagesblatt** — je Messpunkt heutige Messung + „Messung
erfassen" (Wert °C); bei `abweichung` ein prominenter roter **Abweichungs-Kasten** (Soll/Ist + Pflicht zur
**Korrekturmaßnahme**, offen bis gesetzt — single source of truth `offen()`/`offeneAbweichung()` wie der
Trinkwasser-§-51-Workflow). Messhistorie je Messpunkt. Verlinkt aus der Küche-Seite + Nav.

## Tests

`istAbweichung` je art (Kühlung 8 °C → Abweichung, 6 °C ok; TK −15 → Abweichung, −20 ok; Heißhaltung 60 →
Abweichung, 70 ok; Grenzfall exakt am Grenzwert = keine Abweichung). `MessungErfassen` setzt `abweichung`/`offen`
korrekt; Zukunftsdatum → Validierungsfehler. `offeneAbweichung` schließt erst nach Korrekturmaßnahme. Tenant-Scope/IDOR; Gate.
