<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentDiagnosis;

class ConditionMapper
{
    // WHY: amtliches deutsches Codesystem für ICD-10-GM (fhir.de Basisprofile)
    public const ICD10GM_SYSTEM = 'http://fhir.de/CodeSystem/bfarm/icd-10-gm';

    // WHY(Track A Phase 6): ÜLB-Diagnose-Profil geclaimt. KBV verlangt Codesystem-Versionen auf
    // clinicalStatus/verificationStatus (vom Validator vorgegeben) + ICD-10-GM-Version.
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Condition_Medical_Problem_Diagnosis|1.0.0';

    // OPCare-ICD-Katalog ist die 2017er-Baseline (database/data/icd/icd10gm_2017_kodes.csv)
    private const ICD_VERSION = '2017';

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
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'clinicalStatus' => $this->status('http://terminology.hl7.org/CodeSystem/condition-clinical', '3.0.0', 'active', 'Active'),
            'verificationStatus' => $this->status('http://terminology.hl7.org/CodeSystem/condition-ver-status', '2.0.1', 'confirmed', 'Confirmed'),
            'code' => [
                'coding' => [[
                    'system' => self::ICD10GM_SYSTEM,
                    'version' => self::ICD_VERSION,
                    'code' => $d->icdCode->code,
                    'display' => $d->icdCode->bezeichnung,
                ]],
                'text' => $d->icdCode->bezeichnung,
            ],
            'subject' => ['reference' => $patientReference],
        ];

        // WHY(ÜLB): recordedDate ist im Profil verboten (max=0); das Diagnosedatum als onsetDateTime
        // (die dedizierte kbv.basis-Feststellungsdatum-Extension ist eine spätere Verfeinerung).
        if ($d->diagnostiziert_am) {
            $condition['onsetDateTime'] = $d->diagnostiziert_am->toDateString();
        }

        return $condition;
    }

    /** @return array<string, mixed> */
    private function status(string $system, string $version, string $code, string $display): array
    {
        return ['coding' => [['system' => $system, 'version' => $version, 'code' => $code, 'display' => $display]]];
    }
}
