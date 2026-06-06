<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\Resident;

/**
 * Bündelt die konformen Vital-Observations zu einem KBV_PR_MIO_ULB_DiagnosticReport_Vital_Signs_and_Body_Measures
 * (Ziel der Composition-Sektion "vitalparameter"). result referenziert ausschließlich profil-konforme
 * Vital-Observations (Temperatur/Schmerz bleiben außen vor).
 */
class VitalSignsReportMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    public static function id(Resident $r): string
    {
        return 'vital-signs-report-'.$r->id;
    }

    /**
     * @param  array<int, string>  $resultRefs  Referenzen auf konforme Vital-Observations (min 1)
     * @return array<string, mixed>
     */
    public function build(Resident $r, string $patientReference, string $performerReference, string $date, array $resultRefs): array
    {
        return [
            'resourceType' => 'DiagnosticReport',
            'id' => self::id($r),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_DiagnosticReport_Vital_Signs_and_Body_Measures|1.0.0']],
            'status' => 'final',
            'code' => ['coding' => [[
                'system' => self::SNOMED, 'version' => self::SNOMED_VERSION,
                'code' => '1184593002', 'display' => 'Vital sign document section (record artifact)',
            ]]],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $date,
            'performer' => [['reference' => $performerReference]],
            'result' => array_map(fn (string $ref) => ['reference' => $ref], $resultRefs),
        ];
    }
}
