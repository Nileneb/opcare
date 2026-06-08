# Brandschutz-Organisation (§ 10 ArbSchG / ASR A2.2/A2.3 / DIN 14096)

Die **organisatorische** Brandschutz-Ebene: Brandschutzordnung als Dokument, wiederkehrende Begehungs-Eigenkontrolle
mit Mängel-Workflow und Räumungs-/Evakuierungsübung mit Frist-Ampel. Eigene Domäne `app/Domains/Brandschutz`,
Route `/brandschutz`.

> **Abgrenzung (keine Redundanz):**
> - Die **Technik-Prüfung** (Feuerlöscher, Brandmeldeanlage, RWA) liegt im **Wartungsplan**
>   ([Haustechnik](../app/Domains/Facility/Models/FacilityAsset.php), `AssetKategorie::Brandschutz`, Prüffrist-Ampel).
> - Die **Brandschutzhelfer-Ausbildung** je Mitarbeiter:in liegt in den
>   [Arbeitsschutz-Nachweisen](../app/Livewire/Personnel/Arbeitsschutz.php) (`NachweisTyp::Brandschutzhelfer`, ASR A2.2).
>
> Dieses Modul ergänzt rein die betrieblich-organisatorische Ebene.

## Norm-Anker

- **§ 10 ArbSchG** „Erste Hilfe und sonstige Notfallmaßnahmen" — Maßnahmen für **Brandbekämpfung und Evakuierung**
  treffen + zuständige Beschäftigte benennen.
- **ArbStättV Anhang 2.2/2.3 + ASR A2.2 / A2.3** — Brandschutzmaßnahmen, Flucht-/Rettungswege,
  **Räumungsübungen in angemessenen Zeitabständen**.
- **DIN 14096** „Brandschutzordnung" (Teil A Aushang / Teil B alle Beschäftigten / Teil C besondere Aufgaben),
  empfohlene Überprüfung alle 2 Jahre.
- **DGUV Information 205-001** — regelmäßige betriebliche Eigenkontrolle/Begehung.
- Heime sind **Sonderbauten** (Landesbauordnung) → behördliche Brandverhütungsschau; die hier geführte
  Eigenkontroll-Begehung ist die betriebliche Vorstufe/Nachweisgrundlage.

> **Ehrlich:** opcare führt Brandschutzordnung (Dokument + Revisions-Ampel), Begehungs-Protokolle mit
> Mängel-Workflow und Räumungsübungs-Nachweise mit Frist-Ampel. Die behördliche Brandverhütungsschau, die
> bauliche Ertüchtigung und die Anlagen-Wartung bleiben außerhalb (Wartungsplan/Behörde).

## Modell

- **`Brandschutzordnung`** (DIN 14096) — Dokument-mit-Freigabe (gespiegelt vom Hygieneplan): `teil` (A/B/C),
  `version`, `freigegeben_am`, `revision_intervall_monate` (Default 24). **Revisions-Ampel** `status()`
  (entwurf/ueberfaellig/faellig/aktuell) + `ampel()`.
- **`Brandschutzbegehung`** — ein Begehungs-/Eigenkontroll-Protokoll je `bereich`. **Frist-Ampel**
  `faelligkeitsStatus()` (überfällig rot / ≤ 30 Tage gelb / grün). SSOT `offeneMaengel()` (alle Mängel mit
  `behoben_am IS NULL`), `hatOffeneMaengel()` delegiert, `hoechsteOffeneSchwere()`.
- **`Brandschutzmangel`** — festgestellter Mangel: `schwere` (Gering/Wesentlich/Kritisch), `frist`,
  `behoben_am`. `istOffen()`.
- **`Raeumungsuebung`** (§ 10 ArbSchG / ASR A2.3) — `durchgefuehrt_am`, `szenario`, `teilnehmer_anzahl`,
  `dauer_minuten`, `erkenntnisse`, `intervall_monate` (Default 12). Frist-Ampel.

## Service

- **`BrandschutzMonitor`** — die EINE Quelle der Übersichts-Badges: `aktuelleBegehungen()` (je Bereich die
  **jüngste**, Max-Semantik per `begangen_am` + id-Tie-Break), `aktuelleUebung()`, `offeneMaengelAnzahl()`,
  `ueberfaelligeAnzahl()` (überfällige Ordnungen + jüngste Begehungen je Bereich + jüngste Übung; Frist-Status nur
  aus Model-Methoden).

## Workflow

1. **Brandschutzordnung** anlegen (Teil A/B/C, Version, Intervall) → **freigeben** (startet die Revisions-Uhr).
2. **Begehung** je Bereich erfassen (Datum nicht in der Zukunft) → festgestellte **Mängel** mit Schwere + Frist;
   Mangel als **behoben** markieren. Die jüngste Begehung je Bereich treibt die Frist-Ampel.
3. **Räumungsübung** dokumentieren (Szenario, Teilnehmer, Dauer, Erkenntnisse); die jüngste treibt die Frist.

## Spec

`docs/superpowers/specs/2026-06-08-brandschutz-organisation-design.md`
