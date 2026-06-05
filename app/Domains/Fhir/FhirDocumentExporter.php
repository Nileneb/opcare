<?php

namespace App\Domains\Fhir;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\Mappers\CarePlanMapper;
use App\Domains\Fhir\Mappers\CompositionMapper;
use App\Domains\Fhir\Mappers\ConditionMapper;
use App\Domains\Fhir\Mappers\ObservationMapper;
use App\Domains\Fhir\Mappers\PatientMapper;
use App\Domains\Masterdata\Models\Resident;
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
    ) {}

    /** @return array<string, mixed> */
    public function export(Resident $resident): array
    {
        $resident->loadMissing('diagnoses.icdCode');
        $base = rtrim(config('app.url'), '/').'/fhir/';
        $patientRef = $base.'Patient/'.PatientMapper::id($resident);
        $date = Carbon::now()->toIso8601String();

        $entry = [];        // Composition wird vorne eingefügt
        $conditionRefs = [];
        $observationRefs = [];
        $carePlanRef = null;

        foreach ($resident->diagnoses as $diagnosis) {
            if (! $diagnosis->icdCode) {
                continue;
            }
            $resource = $this->conditionMapper->map($diagnosis, $patientRef);
            $ref = $base.'Condition/'.$resource['id'];
            $conditionRefs[] = $ref;
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
            $resource = $this->observationMapper->map($vital, $patientRef);
            $ref = $base.'Observation/'.$resource['id'];
            $observationRefs[] = $ref;
            $entry[] = ['fullUrl' => $ref, 'resource' => $resource];
        }

        $reports = CareReport::query()->where('resident_id', $resident->id)->current()->latest('datum')->get();
        $composition = $this->compositionMapper->map(
            $resident, $patientRef, $date, $reports, $conditionRefs, $carePlanRef, $observationRefs,
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
