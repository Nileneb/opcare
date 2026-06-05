<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentDiagnosis;

class ConditionMapper
{
    // WHY: amtliches deutsches Codesystem für ICD-10-GM (fhir.de Basisprofile)
    public const ICD10GM_SYSTEM = 'http://fhir.de/CodeSystem/bfarm/icd-10-gm';

    public static function id(ResidentDiagnosis $d): string
    {
        return 'condition-'.$d->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentDiagnosis $d, string $patientReference): array
    {
        $condition = [
            'resourceType' => 'Condition',
            'id' => self::id($d),
            'clinicalStatus' => [
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code' => 'active',
                ]],
            ],
            'code' => [
                'coding' => [[
                    'system' => self::ICD10GM_SYSTEM,
                    'code' => $d->icdCode->code,
                    'display' => $d->icdCode->bezeichnung,
                ]],
                'text' => $d->icdCode->bezeichnung,
            ],
            'subject' => ['reference' => $patientReference],
        ];

        if ($d->diagnostiziert_am) {
            $condition['recordedDate'] = $d->diagnostiziert_am->toDateString();
        }

        return $condition;
    }
}
