# Spec: ÜLB-MIO Gap-Analyse + zukunftsfester DB-Fahrplan

**Stand:** 2026-06-05 · **Track A (Daten-Konformität)** · **Quelle:** `kbv.mio.ueberleitungsbogen` 1.0.0
(FHIR R4; Deps `kbv.basis 1.3.0`, `de.basisprofil.r4 1.3.2`). Package lokal extrahiert + introspiziert.

## Warum dieser MIO

Der **PIO Überleitungsbogen** (ÜLB) ist das veröffentlichte FHIR-MIO der Pflegeüberleitung —
das erste Pflege-PIO für die ePA, auf Basis des HL7-DE-„ePflegebericht"-CDA-Leitfadens. Damit ist
das früher offene Track-A-Ziel **präzise normiert und frei verfügbar**: ein exakt beschriebenes
Anforderungsprofil schlägt jede selbstgeschriebene Spec. Wir richten das opcare-Datenmodell darauf aus,
ohne `meta.profile` verfrüht zu claimen (CI bleibt grün, schrittweise Konformität).

## Struktur des Pakets

- **90 Ressourcen-Profile** + 19 Extensions + 56 ValueSets + 54 CodeSystems.
- **Composition `KBV_PR_MIO_ULB_Composition` mit 41 Sektionen** (das Dokument-Rückgrat).
- **59 der 90 Profile sind `Observation`** — das strukturierte Pflege-Assessment (Barthel, Vitalwerte,
  Orientierung, Kontinenz, Ernährung, Atmung …). Genau die „Expertenstandard"-Domäne — hier FHIR-normiert.

## Architektur-Leitsatz

**Zukunftsfest ≠ 90 Tabellen.** opcare hat bereits ein generisches **Assessment-/Instrument-System**
(`instruments`/`instrument_items`/`assessments`/`assessment_answers`) + einen Vitalwert-Store
(`vital_readings`) + Maßnahmen/Ereignisse. Die 59 Observations sind großteils Assessment-Items oder
Vitalwerte — sie brauchen **keine** eigenen Tabellen, sondern Codes (LOINC/ÜLB) + seedbare Instrumente.
Echte Schema-Lücken sind nur dort, wo gar kein Store existiert (Allergien, Medizinprodukte, strukturierte
Kontinenz/Ernährung, soziale Felder).

## Gap-Matrix (41 ÜLB-Sektionen → opcare)

### ✅ Abgedeckt (strukturierte Entsprechung + FHIR-Mapping vorhanden)

| ÜLB-Sektion | opcare-Artefakt | FHIR |
|---|---|---|
| `pflegegrad` (Care_Level) | `Resident.pflegegrad` | (Observation/Extension) |
| `vitalparameter` | `VitalReading` (Gewicht, Blutdruck, Puls, Temp, BZ, SpO2, Atemfreq, Schmerz, **Körpergröße** neu) | Observation ✅ |
| `medikationsplan` | `Prescription` (+schedules, PZN/ATC) | MedicationStatement ✅ |
| `pflegerischeMassnahme` | `CareMeasure` | CarePlan ✅ |
| `anzahlStuerzeLetzte6Monate` (Falls_Last_6_Months) | `CareEvent` Sturz (strukturiert: Anzahl/Fraktur) | (QDVS 71/72) ✅ |
| `freiheitsentziehendeMassnahmen` | `CareEvent` FEM | — |
| `allergienUndUnvertraeglichkeiten` | **`ResidentAllergy`** (neu 2026-06-05) | **AllergyIntolerance ✅** |

### 🟡 Teilweise (Daten da, aber nicht ÜLB-strukturiert/-codiert)

| ÜLB-Sektion | opcare heute | Lücke |
|---|---|---|
| `risiken` / `Risk` | `RiskItem` (Braden/Sturz aus SIS) | nicht FHIR-codiert, nur 2 Instrumente |
| `probleme` | SIS-Assessment-Themenfelder | frei, nicht als codierte Problem-Observation |
| `funktionsbeurteilungen` | Assessment-Engine vorhanden | **kein Barthel-Index geseedet** |
| `schmerzsymptomatik` | `VitalType::Schmerz` (NRS 0–10) | keine differenzierte Schmerz-Erhebung |
| `ernaehrung` | Gewicht + Körpergröße (BMI möglich) | keine Kostform/Applikationsform/MNA |
| `pflegeDurchAngehoerige`, `benachrichtigung…`, `…GesetzlicheBetreuung` | `Custodian` | kein RelatedPerson-Modell |
| `krankenhausaufenthalt` | — | kein strukturierter Encounter |

### 🔴 Fehlend (kein Store)

`medizinprodukte` (Device) · `atemwegszugang` / `atmungsunterstuetzung` / `qualitativeBeschreibungAtmung` ·
`harnkontinenzDifferenzierteEinschaetzung` / `harnableitung` / `zeitpunktLetzteMiktion` ·
`stuhlkontinenz…` / `stuhlableitung` / `zeitpunktLetzterStuhlgang` · `orientierungPsyche` /
`Cognitive_Awareness` · `auffaelligesVerhalten` · `raeumlicheIsolation` · `patientenwunsch` /
`Personal_Statements` · `relevanteInformationsquellen` · `empfehlung` · `mitgegebeneDokumente…` ·
`gradDerBehinderung` · administrative Flags (`mitgabeKrankenkassenkarte`, `zuzahlungsbefreiung`).

## Phasierter DB-Fahrplan (Regret-minimal, jede Phase voll verdrahtet)

- **Phase 1 — erledigt.** Sturz strukturiert (Vorsession) · **Allergien** (`resident_allergies` + AllergyIntolerance) ·
  **Körpergröße**-Vitaltyp (LOINC 8302-2, ermöglicht BMI/Ernährung). *Alles UI + FHIR + Tests.*
- **Phase 2 — erledigt (Assessment-Hebel, keine neuen Tabellen):** **Barthel-Index** als geseedetes
  Instrument (10 Items + Summe) → ÜLB `funktionsbeurteilungen`. `loinc`-Spalte auf `instruments` +
  `instrument_items` macht das generische Modell FHIR-adressierbar (LOINC je Item + Total 96761-2,
  authoritative aus dem ÜLB-Package). Neuer `RiskType::Mobilitaet` (eskaliert bewusst nicht).
  FHIR `AssessmentObservationMapper` (Item-Observations `category=survey` + Summe mit `hasMember`) +
  Composition-Sektion „Funktionsbeurteilungen". **Rendert ohne UI-Code-Änderung** über die vorhandene
  Assessment-Engine (Screenshots im [Projekt-Wiki](https://github.com/Nileneb/opcare/wiki)). HL7-Validator 0 errors.
  *Offen Phase 2b:* Orientierung/Kognition als weitere Instrumente.
- **Phase 2b/3 — erledigt:** generischer **Status-Observation-Mechanismus** (`resident_status_observations`
  + `StatusObservationCatalog`, SNOMED-codiert/Freitext): Bewusstsein, Harn-/Stuhlkontinenz, Kostform,
  Ernährungsform, Atmung. UI-Card „Pflegerische Einschätzungen", FHIR `valueCodeableConcept`/`valueString`,
  dynamische Composition-Sektionen. *Offen:* Drainage + post-koordinierte SNOMED-Ausdrücke (Isolation).
- **Phase 4 — erledigt:** Medizinprodukte/Hilfsmittel (`resident_devices`) → FHIR `Device` (type.text +
  patient), Sektion „Medizinprodukte". *Offen:* Atemwegszugang/-unterstützung als eigene Observations.
- **Phase 5 — erledigt:** Angehörige/Kontaktpersonen (`resident_contacts`) → FHIR `RelatedPerson`,
  Sektion „Angehörige / Kontaktpersonen". *Offen:* Patientenwunsch, Dokumentenmitgabe, gesetzl. Betreuung strukturiert.
- **Phase 6 — Konformität (läuft, iterativ):**
  - ✅ Schritt 1: **nicht-blockierender** ÜLB-Konformitäts-Job im `fhir-validate`-Workflow
    (`-ig kbv.mio.ueberleitungsbogen#1.0.0 -profile KBV_PR_MIO_ULB_Bundle`, `continue-on-error`) — macht
    den Backlog im CI-Log sichtbar, ohne das grüne Basis-Gate (R4 + de.basisprofil) zu brechen.
  - **Gemessenes Gap (Bundle-Ebene):** 2 Fehler — (a) `Bundle.meta` (ÜLB verlangt `meta.profile`),
    (b) Constraint `TypeComposition` „genau eine ÜLB-konforme Composition". Jeder Fehler ist ein Tor zu
    tieferen Profil-Anforderungen (meta.profile je Ressource → KBV-Basisprofile → Slices/Identifier).
    *Bewusst noch nicht geclaimt:* `meta.profile` jetzt zu setzen würde Konformität behaupten UND das grüne
    Gate brechen. Reihenfolge: Inhalte konform machen → dann Profil claimen.
  - ✅ Schritt 2: **Patient** ist ÜLB-konform (`meta.profile` = `KBV_PR_MIO_ULB_Patient`, Name als
    family/given statt Freitext, Custom-Identifier entfernt). Das Base-Gate lädt jetzt das ÜLB-Paket mit,
    sodass das geclaimte Profil im **blockierenden** Gate erzwungen wird — Gesamt-Bundle weiter 0 errors.
    *Offen:* GKV-KVNR-Identifier-Slice (opcare speichert KVNR nicht am Resident → spätere Iteration).
  - ⬜ Schritt 3+: nächste Ressourcen (Composition, Condition, …) sektionsweise `meta.profile`.
  - **Tooling-Hinweis:** `kbv.basis 1.3.0` erzeugt im aktuellen Validator einen Snapshot-Fehler
    (`Same id 'Observation.dataAbsentReason'`) — bekannte KBV/Validator-Inkompatibilität, nicht unsere Daten.

**Dokument-Stand:** 13 Composition-Sektionen (Diagnosen, Allergien, Medikation, Pflegeplan, Vitalwerte,
Funktionsbeurteilungen, Atmung, Bewusstsein/Orientierung, Kontinenz, Ernährung, Medizinprodukte,
Angehörige/Kontaktpersonen, Verlauf). HL7-Validator 0 errors gegen R4 + de.basisprofil.r4#1.5.0.

## Nächster mechanischer Schritt zur vollen Konformität

Package liegt bereit (`/tmp/ulb` bzw. via `packages.simplifier.net/kbv.mio.ueberleitungsbogen/1.0.0`).
Sobald genug Sektionen gefüllt sind: ÜLB-IG in den Validator laden, unser Bundle mit ÜLB-`meta.profile`
prüfen — die Fehlerliste **ist** der Feinschliff-Backlog (Must-Support-Felder, Slices, ValueSet-Bindungen).

**Nicht jetzt voll claimen:** würde das grüne CI-Gate brechen, da unsere generischen Ressourcen die
ÜLB-Must-Support-Constraints noch nicht erfüllen. Erst Daten füllen → dann Profile claimen.
