# Fortbildungsplan (QPR QB6 / § 132a SGB V)

Umgesetzt 2026-06-06. Macht die Fortbildungspflicht des Trägers operativ — *Nachweis-mit-Frist (Ampel)* je
Mitarbeiter:in, mit einer Pflicht-Themen-Matrix als Auf-einen-Blick-Status.

## Rechtsgrundlage

Regelmäßige Fortbildung ist Qualitätskriterium nach **QPR QB6** („Fortbildungsplanung und Nachweise je
Mitarbeiter:in"), den **§ 132a SGB V**-Rahmenverträgen und dem **Landesheimrecht/WTG**. Einzelne Pflichtthemen
haben eigene Grundlagen: Hygiene (§ 23 IfSG), Datenschutz (Art. 32 DSGVO / § 53 BDSG), Gewaltprävention
(§ 113 SGB XI), Brandschutzunterweisung (§ 12 ArbSchG / ASR A2.2), Reanimation (GRC/ERC). Recherche:
[recherche-offene-punkte-2026-06.md §7](recherche-offene-punkte-2026-06.md).

Abgegrenzt von den [Arbeitsschutz-Nachweisen](arbeitsschutz-nachweise.md) (Unterweisung/Vorsorge/Erste
Hilfe/Brandschutzhelfer/BEM) und dem [Skill-Baum](skill-baum.md) (formale Qualifikationen) — der
Fortbildungsplan führt die wiederkehrenden Schulungen und fachlichen Fortbildungen.

## App-Logik

`FortbildungsThema` (Enum) trennt **Pflichtthemen** mit Wiederholungsintervall (Hygiene/Datenschutz/Brandschutz/
Reanimation 12 Monate, Gewaltschutz 24) von **fachlichen** Themen (anlassbezogen, kein Intervall: Sturz,
Dekubitus, Schmerz, Demenz, Ernährung, Palliativ, Kinästhetik, …). `pflicht()` = `intervallMonate() !== null`.

`Fortbildung` je Mitarbeiter:in (Thema, Titel, Anbieter, `geplant_am`, `absolviert_am`, Umfang, `pflicht`,
`intervall_monate`). Status/Ampel:
- nicht absolviert → `geplant` (grau); geplant & überfällig → rot
- absolviert, Pflicht: `absolviert_am` + Intervall ergibt die **Auffrischungs-Fälligkeit** (`ueberfaellig` → rot,
  `faellig` (≤ 60 Tage) → amber, sonst grün)
- absolviert, fachlich → grün

## Pflicht-Themen-Matrix

Die Oberfläche zeigt je Mitarbeiter:in × Pflichtthema die jüngste absolvierte Fortbildung mit Auffrischungs-Ampel.
**Ein Pflichtthema, das eine Person nie absolviert hat, ist rot („fehlt")** — die Summe der Lücken ist der
Handlungsbedarf des Trägers. Das Pflicht-Intervall wird je Eintrag aus dem Thema vorbelegt, bleibt überschreibbar.

## Datenmodell & Zugriff

- `fortbildungen` (Domain `Personnel`), tenant-gescopt über `BaseModel`.
- Oberfläche `app/Livewire/Personnel/Fortbildungsplan.php` (Route `personnel.fortbildung`, Nav „Fortbildung") —
  nur Leitung (`admin`/`pflegefachkraft`/super-admin).

Screenshot: `storage/app/shots/fortbildung.png`. Tests: `tests/Feature/Personnel/FortbildungsplanTest.php`.
