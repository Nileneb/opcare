# QDVS-Referenzdaten (aus DAS-Pflege-Spezifikation V03.0)

Übernommen aus dem OPDE-Projekt (Offene-Pflege.de), das diese öffentliche
Spezifikation der **Datenauswertungsstelle (DAS) Pflege** bündelte.

## `das_plausibilitaetsregeln_v03.csv` (440 Regeln)

Spalten: `dataset;rule_id;assert_test;rule_text;rule_type`

- `assert_test`: XPath-Ausdruck gegen das DAS-Erhebungs-XML, der die **Fehler­bedingung** beschreibt (trifft er zu → Verstoß).
- `rule_text`: Klartext-Meldung (deutsch).
- `rule_type`: `ERROR` (blockt die Abgabe) oder `WARN`.

**Status / Verwendung:** Maßgebliche Quelle für die vollständige QDVS-Konformität.
Die Regeln prüfen das offizielle DAS-XML — unser `App\Domains\Qdvs\Services\QdvsValidator`
prüft aktuell das `QdvsResidentPackage` und deckt nur die wichtigsten Pflichtfeld-Regeln ab.
Für volle Konformität (XML-Export + alle 440 Regeln) ist dies die Vorlage; die Migration
einzelner Regeln erfolgt schrittweise (siehe Plan 7).

## Nicht übernommen

- **Stichtage/Fristen je Bundesland**: in OPDE als sprawlende, vorberechnete Matrix
  (~56k Zeilen) hinterlegt — für OPCare nicht sinnvoll als Datei. Den Stichtag wählt
  die Leitung im QDVS-Export selbst (`Cohort::atStichtag`); die offiziellen Fristen­
  fenster stehen in der DAS-Spezifikation.
- **ICD-10-Katalog**: OPDE bündelt KEINEN — dort Laufzeit-Import. Für OPCare separat aus
  einer ICD-10-GM-Quitte (z. B. BfArM/DIMDI-Stammdatei) zu beziehen.
