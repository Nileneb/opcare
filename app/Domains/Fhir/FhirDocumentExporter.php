<?php

namespace App\Domains\Fhir;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\Mappers\AllergyIntoleranceMapper;
use App\Domains\Fhir\Mappers\AssessmentObservationMapper;
use App\Domains\Fhir\Mappers\CarePlanMapper;
use App\Domains\Fhir\Mappers\CompositionMapper;
use App\Domains\Fhir\Mappers\ConditionMapper;
use App\Domains\Fhir\Mappers\DeviceMapper;
use App\Domains\Fhir\Mappers\DocumentingEntityMapper;
use App\Domains\Fhir\Mappers\MedicationStatementMapper;
use App\Domains\Fhir\Mappers\ObservationMapper;
use App\Domains\Fhir\Mappers\PatientMapper;
use App\Domains\Fhir\Mappers\RelatedPersonMapper;
use App\Domains\Fhir\Mappers\StatusObservationMapper;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\VitalReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Baut ein FHIR-R4-Document-Bundle (Composition + Patient + Conditions + CarePlan + Observations)
 * für einen Bewohner. Vorstufe Richtung ePflegebericht-MIO (mio42/KBV) — dieselbe Dokument-Bundle-Form.
 * Validiert im CI gegen FHIR R4 + de.basisprofil.r4.
 */
class FhirDocumentExporter
{
    public function __construct(
        private readonly PatientMapper $patientMapper,
        private readonly ConditionMapper $conditionMapper,
        private readonly CompositionMapper $compositionMapper,
        private readonly CarePlanMapper $carePlanMapper,
        private readonly ObservationMapper $observationMapper,
        private readonly MedicationStatementMapper $medicationMapper,
        private readonly AllergyIntoleranceMapper $allergyMapper,
        private readonly AssessmentObservationMapper $assessmentMapper,
        private readonly StatusObservationMapper $statusMapper,
        private readonly DeviceMapper $deviceMapper,
        private readonly RelatedPersonMapper $relatedPersonMapper,
        private readonly DocumentingEntityMapper $documentingEntityMapper,
    ) {}

    /** @return array<string, mixed> */
    public function export(Resident $resident): array
    {
        $resident->loadMissing('diagnoses.icdCode', 'allergies', 'statusObservations', 'devices', 'contacts', 'tenant');
        $base = rtrim(config('app.url'), '/').'/fhir/';
        $patientRef = $base.'Patient/'.PatientMapper::id($resident);
        $date = Carbon::now()->toIso8601String();

        $entry = [];        // Composition wird vorne eingefügt
        $conditionRefs = [];
        $observationRefs = [];
        $carePlanRef = null;

        // Dokumentierende Einheit (Organization/Practitioner/PractitionerRole) — einmal je Bundle,
        // als Pflicht-recorder/performer/author wiederverwendet (KBV-MIO-Anforderung).
        $documenting = $this->documentingEntityMapper->build((string) ($resident->tenant?->name ?? ''), $base);
        $entry = [...$entry, ...$documenting['entries']];
        $recorderRef = $documenting['recorderReference'];

        foreach ($resident->diagnoses as $diagnosis) {
            if (! $diagnosis->icdCode) {
                continue;
            }
            $resource = $this->conditionMapper->map($diagnosis, $patientRef);
            $ref = $base.'Condition/'.$resource['id'];
            $conditionRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }

        $allergyRefs = [];
        foreach ($resident->allergies as $allergy) {
            $resource = $this->allergyMapper->map($allergy, $patientRef, $recorderRef);
            $ref = $base.'AllergyIntolerance/'.$resource['id'];
            $allergyRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }

        $medicationRefs = [];
        $prescriptions = Prescription::query()->where('resident_id', $resident->id)
            ->aktiv()->with(['medProduct', 'schedules'])->get();
        foreach ($prescriptions as $prescription) {
            $resource = $this->medicationMapper->map($prescription, $patientRef);
            $ref = $base.'MedicationStatement/'.$resource['id'];
            $medicationRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }

        $measures = $resident->careMeasures()->current()->latest('id')->get();
        if ($measures->isNotEmpty()) {
            $resource = $this->carePlanMapper->map($resident, $measures, $patientRef);
            $carePlanRef = $base.'CarePlan/'.$resource['id'];
            $entry[] = ['fullUrl' => $carePlanRef, 'resource' => $resource];
        }

        // WHY: jüngster Messwert je Vitaltyp als Snapshot — kein Dump der gesamten Historie
        $vitals = VitalReading::query()->where('resident_id', $resident->id)
            ->latest('gemessen_am')->get()->unique('typ');
        foreach ($vitals as $vital) {
            $resource = $this->observationMapper->map($vital, $patientRef, $recorderRef);
            $ref = $base.'Observation/'.$resource['id'];
            $observationRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }

        // WHY: jüngstes LOINC-codiertes Assessment (z. B. Barthel) → ÜLB-Sektion funktionsbeurteilungen
        $functionalRefs = [];
        $assessment = Assessment::query()->where('resident_id', $resident->id)->where('status', 'aktiv')
            ->whereHas('instrument', fn ($q) => $q->whereNotNull('loinc'))
            ->with(['instrument', 'answers.instrumentItem'])
            ->latest('durchgefuehrt_am')->first();
        if ($assessment) {
            $effective = $assessment->durchgefuehrt_am?->toIso8601String() ?? $date;
            $memberRefs = [];
            foreach ($assessment->answers as $answer) {
                if (! $answer->instrumentItem?->loinc) {
                    continue;
                }
                $resource = $this->assessmentMapper->itemObservation($answer, $patientRef, $effective);
                $ref = $base.'Observation/'.$resource['id'];
                $memberRefs[] = $ref;
                $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
            }
            $total = $this->assessmentMapper->totalObservation($assessment, $patientRef, $effective, $memberRefs);
            $totalRef = $base.'Observation/'.$total['id'];
            $functionalRefs[] = $totalRef;
            $entry[] = ['fullUrl' => $totalRef, 'resource' => $total];
        }

        // WHY(ÜLB): codierte Status-Observations (Bewusstsein/Kontinenz/Ernährung/Atmung), jüngster je Typ;
        // gruppiert in Composition-Sektionen gemäß StatusObservationCatalog.
        $extraSections = [];
        $statusObs = $resident->statusObservations->sortByDesc('erfasst_am')->unique('typ');
        foreach ($statusObs as $status) {
            $resource = $this->statusMapper->map($status, $patientRef, $status->erfasst_am?->toIso8601String() ?? $date);
            if ($resource === null) {
                continue;
            }
            $ref = $base.'Observation/'.$resource['id'];
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
            $extraSections[$this->statusMapper->section($status)][] = $ref;
        }

        foreach ($resident->devices as $device) {
            $resource = $this->deviceMapper->map($device, $patientRef);
            $ref = $base.'Device/'.$resource['id'];
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
            $extraSections['Medizinprodukte'][] = $ref;
        }

        foreach ($resident->contacts as $contact) {
            $resource = $this->relatedPersonMapper->map($contact, $patientRef);
            $ref = $base.'RelatedPerson/'.$resource['id'];
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
            $extraSections['Angehörige / Kontaktpersonen'][] = $ref;
        }

        $reports = CareReport::query()->where('resident_id', $resident->id)->current()->latest('datum')->get();
        $composition = $this->compositionMapper->map(
            $resident, $patientRef, $date, $reports, $conditionRefs, $carePlanRef, $observationRefs, $medicationRefs, $allergyRefs, $functionalRefs, $extraSections,
        );

        array_unshift(
            $entry,
            ['fullUrl' => $base.'Composition/'.$composition['id'], 'resource' => $composition],
            ['fullUrl' => $patientRef, 'resource' => $this->patientMapper->map($resident)],
        );

        return [
            'resourceType' => 'Bundle',
            'id' => 'opcare-pflegebericht-'.$resident->id,
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
