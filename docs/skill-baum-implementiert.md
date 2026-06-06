# Skill-Baum, Berechtigungsmatrix, Delegation & Beauftragten-Register (implementiert)

Umsetzung der [Spezifikation](skill-baum-berechtigungsmatrix-spec.md), Schritte 1–4. „Wer darf was" ist scharf
abgebildet; alles datengetrieben + je Einrichtung erweiterbar. Screenshots: Wiki **Skill Baum Berechtigungsmatrix**.

## 1. Skill-Baum (Kompetenz-Katalog + Mitarbeiter-Kompetenz) — `/personal/kompetenzen`
- `Kompetenz` (Katalog, `KompetenzDefaults`: Grundberufe/Weiterbildungen/interne Schulungen) mit **Voraussetzungen
  (DAG)** und Gültigkeit/Auffrischung. `MitarbeiterKompetenz` mit `gueltig_bis` → **dieselbe Fälligkeits-Ampel** wie
  die Arbeitsschutz-Nachweise. Beim Erteilen wird der Voraussetzungs-Graph geprüft (z. B. „Wundexperte ICW" nur mit „Pflegefachkraft").

## 2. Berechtigungsmatrix + `Befugnis`-Service — `/personal/berechtigungen`
- `Taetigkeit` (Katalog, `TaetigkeitDefaults`, 16 Tätigkeiten) mit `nur_fachkraft`, `vorbehaltsaufgabe` (§ 4 PflBG),
  `erforderliche_kompetenz`, `arzt_anordnung_noetig`. `Befugnis::hindernis(User, Taetigkeit)` prüft Qualifikation
  (Fachkraft aus employeeProfile **oder** aktiver `ist_fachkraft`-Kompetenz) + Zusatzkompetenz + gültige Delegation —
  **eine Quelle der Wahrheit** für UI und (später) Doku-Guards. Die Matrix-Seite ist der operative Caller.

## 3. Delegation (generisch, domänenübergreifend) — Teil von `/personal/berechtigungen`
- `Delegation` (Anordner, Nehmer, Tätigkeit, optional polymorpher Bezug Bewohner/Anlage, Befristung, Widerruf).
  Trägt Pflege (Arzt→Pflegekraft) **und** Haustechnik (Betreiber→befähigte Person) **und** Küche. Aktive Delegation
  schaltet die delegationspflichtige Tätigkeit im `Befugnis`-Service frei; abgelaufene/widerrufene zählen nicht.

## 4. Beauftragten-Register — `/personal/beauftragte`
- `Beauftragtenrolle` (Katalog, `BeauftragtenrolleDefaults`: 15 Pflicht-Rollen inkl. Hygiene, Brandschutz,
  Datenschutz, Medizinproduktesicherheit, Betriebsarzt/Sifa, **Elektrofachkraft + Leiterbeauftragte:r (DGUV 208-016)**)
  + `Beauftragtenbestellung` (benannte Person + Frist-Ampel). Kopf zeigt **unbesetzte Pflicht-Rollen** (Compliance-Gate) und überfällige Auffrischungen.

## 5. Befugnis-Guards in den Doku-Modulen (greifen jetzt beim Abzeichnen)
Der `Befugnis`-Service ist an die echten Signaturpunkte gekoppelt (`darfKey(User, key)` löst den Katalog auf):
- **SIS/Pflegeplanung** (`ResidentShow::createSis`): Vorbehaltsaufgabe `sis_abzeichnen` → eine Hilfskraft erhält 403,
  eine Pflegefachkraft darf (Integrationstest belegt beides).
- **Medikamentengabe** (`Stellplan::quittieren`): `orale_medikation` (LG1 für Hilfskräfte, Fachkraft inhärent).
- **BtM-Gabe** (`BtmNachweis::buchen`, Vorgang Gabe): `btm_abzeichnen` (nur Fachkraft).

Modell-Feinheit: `Befugnis::istFachkraft` = Rolle (admin/super-admin/pflegefachkraft) **oder** Personalakte-Qualifikation
**oder** aktive `ist_fachkraft`-Kompetenz. Für Fachkräfte sind LG-Kompetenz + Einzel-Delegation **gewaivt** (inhärent
bzw. Anordnung = Verordnung); **Spezialqualifikationen** mit `kompetenz_auch_fuer_fachkraft` (z. B. BEEP-Heilkunde)
gelten auch für Fachkräfte.

## 6. BEEP-Gesetz (ab 1.1.2026) in der Matrix
Drei eigenständige Heilkunde-Tätigkeiten (`beep_wunde`, `beep_diabetes`, `beep_demenz`) sind im Tätigkeitskatalog,
freigeschaltet durch die Kompetenz **`bsc_pflege_heilkundlich`** (auch für reguläre Fachkräfte verlangt).

## Verankerung & Wiederverwendung
Nutzt die Querschnitts-Bausteine **Nachweis-mit-Frist** (Ampel) und **Datei-Upload** (Nachweise). Rollen: Leitung.
Tests: `tests/Feature/Personnel/{SkillBaumTest,BefugnisTest,BefugnisGuardTest,BeauftragtenregisterTest}.php` (15 Tests).
