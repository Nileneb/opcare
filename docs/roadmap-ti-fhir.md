# Roadmap: TI / FHIR / Konformität

**Leitprinzip:** opcare ist ein **Open-Source-Projekt**, kein betriebener regulierter Dienst.
Wir bauen zur **Daten- und Sicherheits-*Form*** der Standards hin — die **Zertifizierungs-
und Anbindungs-Bürokratie** wird aufgeschoben. Solange kein Echtbetrieb mit realen
Patientendaten läuft, besteht **kein regulatorisches Rechtsgate**. Das Projekt selbst ist
unkritisch; Pflichten (DSGVO, ggf. Zulassung) treffen erst den späteren *Betreiber*.

## Drei Tracks

| Track | Inhalt | Status | Rechtsgate |
|---|---|---|---|
| **A — Daten-Konformität** | FHIR R4, deutsche Basisprofile (`de.basisprofil.r4`), **ePflegebericht-MIO** (mio42/KBV, noch nicht final). *ISiK ist krankenhausspezifisch → für Pflege sekundär.* | **läuft** (FHIR-Export + CI-Validierung steht) | nein |
| **B — Security-Hygiene** | Tenant-Isolation, RBAC, Audit-Log, IDOR-Härtung, Dependency-Audit (CVE-Gate ✅), SAST | **viel da**, ausbauen | nein |
| **C — TI-Anbindung + Zulassung** | Online-Auth (GesundheitsID/TI-IDP), KIM, ePA-Schreibzugriff, eVerordnung, Konnektor-Light, gematik-Zulassung, BSI-TR-Konformität | **aufgeschoben** | **ja** |
| **D — Domäne/Fachlichkeit** | Pflege-Fachfunktionen nach **Nationalen Expertenstandards** (Dekubitus/Sturz/Schmerz/Ernährung/Kontinenz) | **blockiert** auf Quelle (Expertenstandards nicht frei verfügbar) | nein |

**Wichtig:** „BSI-TR-Konformität" und „Konnektor-Light-Migration" gehören zu **Track C**
(Anbindung/Zertifizierung) — *nicht* jetzt umbauen (kein TI-Anschluss vorhanden →
„gebaut-aber-ungenutzt"-Risiko). Generelle Security-by-Design = Track B = ja.
Architektur-Vorsorge: Auth-Schicht abstrahiert halten, damit TI-IDP später andockt.

## Externe Termine (Orientierung, kein Gate für uns)

- **ePA-Pflicht:** 07/2025 — Betreiber-Pflicht, nicht Projekt-Pflicht.
- **eVerordnung (Pflege/Hilfsmittel):** 07/2026 — Track C, später.
- **MIO-Reife:** selbst der Laborbefund-MIO wird erst **Herbst 2026** verpflichtend →
  ePflegebericht-MIO ist noch in Entwicklung → **nicht auf bewegliche Spec voll ausbauen**,
  sondern valides generisches FHIR + wachsendes Validierungs-Gate.

## Was bereits steht

- FHIR-R4-**Document-Bundle**-Export (Patient/Condition/Composition) + `fhir:export` +
  Download-Route + **CI-Gate mit amtlichem HL7-Validator** (0 errors).
- QDVS-Engine (DAS-Plausibilität, 52 Regeln aktiv), ICD-10-GM-Katalog, Maßnahmen-Katalog,
  Vorkommnis-Erfassung, strukturierte Dekubitus-Doku.
- Security-Basis: row-level Tenancy + `TenantScope`, spatie-RBAC (Teams je Mandant),
  Audit-Log (`activitylog`), IDOR-Härtung (`tenantExists()`), Policy-Guards.

## Priorisierter Plan (jetzt → später)

### Jetzt (Open-Source, kein Rechtsgate)
1. **[Track D] Domänen-Best-Practice nach Nationalen Expertenstandards** — der substanziellste
   Block, aber **blockiert** auf die Quelle (Expertenstandards nicht frei verfügbar → erst organisieren).
   Erfassungs-/Interventions-Tools (Dekubitus, Sturz, Schmerz, Ernährung, Kontinenz),
   deckt die DAS-Indikatoren *fachlich korrekt* ab.
2. **[Track A] FHIR-Track vertiefen** — mehr Ressourcen (CarePlan aus SIS, Observation aus
   Vitalwerten/Assessments, MedicationStatement) + deutsche Basisprofile (`de.basisprofil.r4`)
   ins CI-Validierungs-Gate. = „ePflegebericht-Schnittstelle früh pilotieren". *(läuft jetzt)*
3. **[Track B] Security** — CVE-Gate ✅ (`composer audit`); als Nächstes SAST + tenancy/RBAC-Härtung.

### Aufgeschoben (Architektur bereithalten, nicht bauen)
- gematik-Zulassung (Doku in `docs/TelematikAntrag/`), BSI-TR-Konformität,
  echte TI-Konnektivität (KIM/ePA/eVerordnung), Test-Infrastruktur (Karten/Referenzumgebung).

## Offene DAS-Detailarbeit (siehe Memory)
- ✅ STURZ (Feld 71: 0/1/2) + STURZFOLGEN (Feld 72) strukturiert erfasst → 5 Plausi-Regeln scharf
  (10055/20056/30075/20057/60039), applicable 52 → 57. Folge-Codes **0=keine/1=Fraktur** verifiziert
  gemappt; Codes 2–4 (SHT etc.) offen → Regeln 30076/60040/70003 bleiben ehrlich ungewertet.
- offen: akute klinische Ereignisse (Apoplex/Fraktur als eigenes Feld 10/…),
  volle Dekubitus-Codesemantik (1-vs-2, LOK), volle Sturzfolgen-Codeliste (2–4) — je sobald amtliche
  DAS-Ausfüllanleitung / Erhebungsinstrument vorliegt.
