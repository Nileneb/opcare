<?php

namespace App\Domains\Fhir;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Fhir\Mappers\CompositionMapper;
use App\Domains\Fhir\Mappers\ConditionMapper;
use App\Domains\Fhir\Mappers\PatientMapper;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Baut ein FHIR-R4-Document-Bundle (Composition + Patient + Conditions) für einen Bewohner.
 * Vorstufe Richtung ePflegebericht-MIO (mio42/KBV) — dieselbe Dokument-Bundle-Form.
 */
class FhirDocumentExporter
{
    public function __construct(
        private readonly PatientMapper $patientMapper,
        private readonly ConditionMapper $conditionMapper,
        private readonly CompositionMapper $compositionMapper,
    ) {}

    /** @return array<string, mixed> */
    public function export(Resident $resident): array
    {
        $resident->loadMissing('diagnoses.icdCode');
        $reports = CareReport::query()
            ->where('resident_id', $resident->id)
            ->current()
            ->latest('datum')
            ->get();

        $base = rtrim(config('app.url'), '/').'/fhir/';
        $patientRef = $base.'Patient/'.PatientMapper::id($resident);
        $date = Carbon::now()->toIso8601String();

        $composition = $this->compositionMapper->map($resident, $reports, $patientRef, $date);

        $entry = [[
            'fullUrl' => $base.'Composition/'.$composition['id'],
            'resource' => $composition,
        ], [
            'fullUrl' => $patientRef,
            'resource' => $this->patientMapper->map($resident),
        ]];

        foreach ($resident->diagnoses as $diagnosis) {
            if (! $diagnosis->icdCode) {
                continue;
            }
            $resource = $this->conditionMapper->map($diagnosis, $patientRef);
            $entry[] = ['fullUrl' => $base.'Condition/'.$resource['id'], 'resource' => $resource];
        }

        return [
            'resourceType' => 'Bundle',
            'id' => 'opcare-pflegebericht-'.$resident->id,
            // WHY(FHIR bdl-9): ein Document-Bundle braucht eine global eindeutige Dokument-Identität
            'identifier' => [
                'system' => 'urn:ietf:rfc:3986',
                'value' => 'urn:uuid:'.Str::uuid()->toString(),
            ],
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
