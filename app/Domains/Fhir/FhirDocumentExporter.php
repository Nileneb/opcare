<?php

namespace App\Domains\Fhir;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\Mappers\AllergyIntoleranceMapper;
use App\Domains\Fhir\Mappers\AssessmentObservationMapper;
use App\Domains\Fhir\Mappers\CareLevelMapper;
use App\Domains\Fhir\Mappers\CompositionMapper;
use App\Domains\Fhir\Mappers\ConditionMapper;
use App\Domains\Fhir\Mappers\DocumentingEntityMapper;
use App\Domains\Fhir\Mappers\MedicationMapper;
use App\Domains\Fhir\Mappers\MedicationStatementMapper;
use App\Domains\Fhir\Mappers\ObservationMapper;
use App\Domains\Fhir\Mappers\PatientMapper;
use App\Domains\Fhir\Mappers\PresenceObservationMapper;
use App\Domains\Fhir\Mappers\ProcedureMapper;
use App\Domains\Fhir\Mappers\VitalSignsReportMapper;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\VitalReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Baut ein ÜLB-MIO-konformes FHIR-R4-Document-Bundle (KBV_PR_MIO_ULB_Bundle) für einen Bewohner:
 * Composition mit den Pflicht- und Kern-Sektionen pflegegrad (Care_Level), vitalparameter (DiagnosticReport),
 * probleme/allergien/medikationsplan/funktionsbeurteilungen (Presence-Wrapper) und pflegerischeMassnahme
 * (Procedure). Im CI gegen FHIR R4 + de.basisprofil.r4 + kbv.mio.ueberleitungsbogen validiert (0 errors).
 */
class FhirDocumentExporter
{
    public function __construct(
        private readonly PatientMapper $patientMapper,
        private readonly ConditionMapper $conditionMapper,
        private readonly CompositionMapper $compositionMapper,
        private readonly ObservationMapper $observationMapper,
        private readonly MedicationStatementMapper $medicationMapper,
        private readonly MedicationMapper $medicationResourceMapper,
        private readonly AllergyIntoleranceMapper $allergyMapper,
        private readonly AssessmentObservationMapper $assessmentMapper,
        private readonly DocumentingEntityMapper $documentingEntityMapper,
        private readonly CareLevelMapper $careLevelMapper,
        private readonly VitalSignsReportMapper $vitalSignsReportMapper,
        private readonly PresenceObservationMapper $presenceMapper,
        private readonly ProcedureMapper $procedureMapper,
    ) {}

    /** @return array<string, mixed> */
    public function export(Resident $resident): array
    {
        $resident->loadMissing('diagnoses.icdCode', 'allergies', 'devices', 'tenant');
        $base = rtrim(config('app.url'), '/').'/fhir/';
        $patientRef = $base.'Patient/'.PatientMapper::id($resident);
        $date = Carbon::now()->toIso8601String();

        $entry = [];                 // Composition + Patient werden am Ende vorne eingefügt
        $sections = [];              // geordnete, slice-konforme Composition-Sektionen

        // Dokumentierende Einheit (Organization/Practitioner/PractitionerRole) — einmal je Bundle,
        // als Pflicht-author/performer wiederverwendet (KBV-MIO-Anforderung).
        $documenting = $this->documentingEntityMapper->build($resident->tenant, $base);
        $entry = [...$entry, ...$documenting['entries']];
        $authorRef = $documenting['recorderReference'];
        $practitionerRef = $documenting['practitionerReference'];

        // Pflicht-Sektion pflegegrad (Care_Level) — immer vorhanden, auch ohne erfassten Grad.
        $careLevel = $this->careLevelMapper->build($resident, $patientRef, $authorRef, $date);
        $careLevelRef = $base.'Observation/'.$careLevel['id'];
        $entry[] = ['fullUrl' => $careLevelRef, 'resource' => $careLevel];
        $sections[] = ['slice' => 'pflegegrad', 'entries' => [$careLevelRef]];

        // vitalparameter: konforme Vital-Observations (jüngste je Typ) → DiagnosticReport-Wrapper.
        // WHY: nur profil-konforme Vitals (mit meta.profile) sind im DiagnosticReport.result zulässig —
        // generische (Temperatur/Schmerz) bleiben außen vor, damit das Document-Bundle erreichbar bleibt.
        $vitals = VitalReading::query()->where('resident_id', $resident->id)
            ->latest('gemessen_am')->get()->unique('typ');
        $vitalResultRefs = [];
        foreach ($vitals as $vital) {
            $resource = $this->observationMapper->map($vital, $patientRef, $authorRef);
            if (! isset($resource['meta'])) {
                continue;
            }
            $ref = $base.'Observation/'.$resource['id'];
            $vitalResultRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }
        if ($vitalResultRefs !== []) {
            $report = $this->vitalSignsReportMapper->build($resident, $patientRef, $authorRef, $date, $vitalResultRefs);
            $reportRef = $base.'DiagnosticReport/'.$report['id'];
            $entry[] = ['fullUrl' => $reportRef, 'resource' => $report];
            $sections[] = ['slice' => 'vitalparameter', 'entries' => [$reportRef]];
        }

        // probleme: Diagnosen (Condition) → Presence_Problems-Wrapper
        $conditionRefs = [];
        foreach ($resident->diagnoses as $diagnosis) {
            if (! $diagnosis->icdCode) {
                continue;
            }
            $resource = $this->conditionMapper->map($diagnosis, $patientRef);
            $ref = $base.'Condition/'.$resource['id'];
            $conditionRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }
        if ($conditionRefs !== []) {
            $presence = $this->presenceMapper->problems('presence-problems-'.$resident->id, $patientRef, $authorRef, $date, $conditionRefs);
            $pRef = $base.'Observation/'.$presence['id'];
            $entry[] = ['fullUrl' => $pRef, 'resource' => $presence];
            $sections[] = ['slice' => 'probleme', 'entries' => [$pRef]];
        }

        // allergien: AllergyIntolerance → Presence_Allergies-Wrapper
        $allergyRefs = [];
        foreach ($resident->allergies as $allergy) {
            $resource = $this->allergyMapper->map($allergy, $patientRef, $authorRef);
            $ref = $base.'AllergyIntolerance/'.$resource['id'];
            $allergyRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }
        if ($allergyRefs !== []) {
            $presence = $this->presenceMapper->allergies('presence-allergies-'.$resident->id, $patientRef, $authorRef, $date, $allergyRefs);
            $pRef = $base.'Observation/'.$presence['id'];
            $entry[] = ['fullUrl' => $pRef, 'resource' => $presence];
            $sections[] = ['slice' => 'allergienUndUnvertraeglichkeiten', 'entries' => [$pRef]];
        }

        // medikationsplan: Medication + MedicationStatement → Information_Medicines-Wrapper
        $medicationRefs = [];
        $prescriptions = Prescription::query()->where('resident_id', $resident->id)
            ->aktiv()->with(['medProduct', 'schedules'])->get();
        foreach ($prescriptions as $prescription) {
            $medication = $this->medicationResourceMapper->map($prescription);
            $medRef = $base.'Medication/'.$medication['id'];
            $entry[] = ['fullUrl' => $medRef, 'resource' => $medication];

            $resource = $this->medicationMapper->map($prescription, $patientRef, $medRef);
            $ref = $base.'MedicationStatement/'.$resource['id'];
            $medicationRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }
        if ($medicationRefs !== []) {
            $presence = $this->presenceMapper->medicines('presence-medicines-'.$resident->id, $patientRef, $authorRef, $date, $medicationRefs);
            $pRef = $base.'Observation/'.$presence['id'];
            $entry[] = ['fullUrl' => $pRef, 'resource' => $presence];
            $sections[] = ['slice' => 'medikationsplan', 'entries' => [$pRef]];
        }

        // funktionsbeurteilungen: jüngstes LOINC-codiertes Assessment (z. B. Barthel) → Assessment_Free-Leaf,
        // referenziert via Presence_Functional_Assessment-Wrapper.
        $assessment = Assessment::query()->where('resident_id', $resident->id)->where('status', 'aktiv')
            ->whereHas('instrument', fn ($q) => $q->whereNotNull('loinc'))
            ->with('instrument')->latest('durchgefuehrt_am')->first();
        if ($assessment) {
            $effective = $assessment->durchgefuehrt_am->toIso8601String();
            // WHY(ÜLB): Assessment_Free.performer akzeptiert nur Practitioner (nicht PractitionerRole)
            $free = $this->assessmentMapper->assessmentFree($assessment, $patientRef, $practitionerRef, $effective);
            $freeRef = $base.'Observation/'.$free['id'];
            $entry[] = ['fullUrl' => $freeRef, 'resource' => $free];

            $presence = $this->presenceMapper->functionalAssessment('presence-functional-'.$resident->id, $patientRef, $authorRef, $date, [$freeRef]);
            $pRef = $base.'Observation/'.$presence['id'];
            $entry[] = ['fullUrl' => $pRef, 'resource' => $presence];
            $sections[] = ['slice' => 'funktionsbeurteilungen', 'entries' => [$pRef]];
        }

        // pflegerischeMassnahme: aktuelle Maßnahmen → je eine Procedure (direkt referenziert)
        $procedureRefs = [];
        foreach ($resident->careMeasures()->current()->latest('id')->get() as $measure) {
            $procedure = $this->procedureMapper->map($measure, $patientRef);
            $procRef = $base.'Procedure/'.$procedure['id'];
            $procedureRefs[] = $procRef;
            $entry[] = ['fullUrl' => $procRef, 'resource' => $procedure];
        }
        if ($procedureRefs !== []) {
            $sections[] = ['slice' => 'pflegerischeMassnahme', 'entries' => $procedureRefs];
        }

        $reports = CareReport::query()->where('resident_id', $resident->id)->current()->latest('datum')->get();
        $composition = $this->compositionMapper->map($resident, $patientRef, $date, $authorRef, $sections, $reports);

        array_unshift(
            $entry,
            ['fullUrl' => $base.'Composition/'.$composition['id'], 'resource' => $composition],
            ['fullUrl' => $patientRef, 'resource' => $this->patientMapper->map($resident)],
        );

        return [
            'resourceType' => 'Bundle',
            'id' => 'opcare-pflegebericht-'.$resident->id,
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Bundle|1.0.0']],
            // WHY(FHIR bdl-9): ein Document-Bundle braucht eine global eindeutige Dokument-Identität
            'identifier' => ['system' => 'urn:ietf:rfc:3986', 'value' => 'urn:uuid:'.Str::uuid()->toString()],
            'type' => 'document',
            'timestamp' => $date,
            'entry' => $entry,
        ];
    }

    public function toJson(Resident $resident): string
    {
        return json_encode($this->export($resident), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
