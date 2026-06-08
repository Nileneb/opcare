<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentHospitalStay;

/**
 * KBV_PR_MIO_ULB_Encounter_Hospital_Stay — zurückliegender stationärer Aufenthalt.
 * Minimal-Profil: status=finished, class fix IMP (v3-ActCode), subject, und ausschließlich period.end
 * (period.start ist im Profil verboten, max=0). reasonCode/type/serviceProvider sind ebenfalls verboten.
 */
class HospitalStayMapper
{
    /** @return array{id:string, resource:array<string,mixed>} */
    public function build(ResidentHospitalStay $stay, string $patientReference): array
    {
        $id = 'encounter-hospital-'.$stay->id;

        return [
            'id' => $id,
            'resource' => [
                'resourceType' => 'Encounter',
                'id' => $id,
                'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Encounter_Hospital_Stay|1.0.0']],
                'status' => 'finished',
                'class' => [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                    'version' => '5.0.0',
                    'code' => 'IMP',
                    'display' => 'inpatient encounter',
                ],
                'subject' => ['reference' => $patientReference],
                'period' => ['end' => $stay->ende->toIso8601String()],
            ],
        ];
    }
}
