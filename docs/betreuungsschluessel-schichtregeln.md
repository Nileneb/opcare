# Betreuungsschlüssel (§ 113c) + ergonomische Schichtregeln

Recherche-Strang B: der Dienstplan zeigt jetzt den **Personalbedarf aus dem Pflegegrad-Mix** (§ 113c SGB XI /
PeBeM) als Soll-vs-Ist-Ampel und prüft die Schichtfolge zusätzlich gegen **arbeitswissenschaftliche
Empfehlungen** (BAuA/BGHM) — beides der harten ArbZG-Prüfung nachgelagert, alles einrichtungsspezifisch einstellbar.

> Screenshot: siehe Wiki-Seite **Betreuungsschluessel Schichtregeln**.

## Betreuungsschlüssel (§ 113c SGB XI)

- **Personalanhaltswerte (PAW)** nach § 113c Abs. 1 (Stand 01.07.2023, bundeseinheitlich) als Code-Konstante
  (`PersonalbemessungDefaults::PAW`): VZÄ je Bewohner × Pflegegrad × Qualifikationsstufe (QN1+2/QN3/QN4).
- **Soll** = Pflegegrad-Mix der belegten Bewohner × PAW × Multiplikator → VZÄ; mal Tarif-Wochenstunden ⇒ Soll-Wochenstunden
  (gesamt + Fachkraft). **Ist** = geplante Wochenstunden aus dem Dienstplan (Fachkraft-Anteil über die Qualifikation
  der Mitarbeitenden). **Ampel** grün/gelb/rot nach Deckungsgrad.
- Einrichtungsspezifisch (`StaffingConfig`, editierbar unter „Regeln"): Tarif-Wochenstunden (1 VZÄ),
  Fachkraftquote, Nachtdienst-Schlüssel (landesrechtlich), **PAW-Multiplikator** (private Häuser mit mehr Personal > 1,0).
- Die PAW-Tabelle bleibt Konstante (bundeseinheitlich, BMG-Review alle 2 J. → `VERSION` hochzählen).

## Ergonomische Schichtregeln (zweite Engine)

Nach der ArbZG-Hartprüfung läuft der `ScheduleQualityAnalyzer` über den Wochenplan (§ 6 ArbZG verweist auf
gesicherte arbeitswissenschaftliche Erkenntnisse). **Bewusst Empfehlungen** (Warnung/Hinweis), editierbar/abschaltbar:

| Regel | Default | Quelle |
|---|---|---|
| Max. aufeinanderfolgende Arbeitstage | > 7 | BAuA/BGHM |
| Max. aufeinanderfolgende Nachtdienste | > 3 | BAuA/BGHM/DGAUM |
| Quick Return (Spät → Früh) | Ruhe < 16 h | BGHM |
| Vorwärtsrotation (keine Rückwärtsfolge) | Hinweis | BAuA/BGHM/DGAUM |
| Zusammenhängende freie Tage (Freiblock) | < 2 Tage/Woche | BGHM/BAuA |

Deckt die geforderten Fälle ab: Freiblöcke, Wochenend-Stückelung (über Freiblock), ständige Früh+Spät-Wechsel
(Quick Return + Vorwärtsrotation), Nachtdienst-Häufung. Regeln, für die noch kein Algorithmus existiert, werden
nicht stumm übersprungen — die Engine wertet nur die aktiven, implementierten Regeln aus und der Katalog ist transparent.

## Architektur

Domäne `App\Domains\Scheduling\Compliance`: `PersonalbemessungDefaults` (PAW), `Betreuungsschluessel` (Service) +
`StaffingAnalysis` (DTO), `ScheduleQualityDefaults`/`ScheduleQualityAnalyzer` + `QualityFinding`, Modelle
`StaffingConfig` und `ScheduleQualityRule`. Eingebettet im **Dienstplan** (Panel + Befundliste); Editoren auf der
**Arbeitsrecht-Regeln**-Seite. Tests: `tests/Feature/Scheduling/BetreuungsschluesselTest.php`,
`ScheduleQualityTest.php`.
