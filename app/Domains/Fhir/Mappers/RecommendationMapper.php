<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentRecommendation;

/**
 * KBV_PR_MIO_ULB_CarePlan_Recommendation_Receiving_Institution — Empfehlung an die aufnehmende Einrichtung.
 * Profil: status=draft, intent=plan, subject + contributor (Pflicht), genau eine activity.detail mit
 * status=not-started. Der Empfehlungstext steht in activity.detail.code.text; das verpflichtende
 * SNOMED-Coding (codeSnomed) kennzeichnet die Aktivität generisch (kein ValueSet gebunden).
 */
class RecommendationMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    /** @return array{id:string, resource:array<string,mixed>} */
    public function build(ResidentRecommendation $rec, string $patientReference, string $contributorReference): array
    {
        $id = 'careplan-recommendation-'.$rec->id;

        return [
            'id' => $id,
            'resource' => [
                'resourceType' => 'CarePlan',
                'id' => $id,
                'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_CarePlan_Recommendation_Receiving_Institution|1.0.0']],
                'status' => 'draft',
                'intent' => 'plan',
                'subject' => ['reference' => $patientReference],
                'contributor' => [['reference' => $contributorReference]],
                'activity' => [[
                    'detail' => [
                        'code' => [
                            'coding' => [[
                                'system' => self::SNOMED,
                                'version' => self::SNOMED_VERSION,
                                'code' => '406216001',
                                'display' => 'Recommendation to caregiver (procedure)',
                            ]],
                            'text' => $rec->empfehlung,
                        ],
                        'status' => 'not-started',
                    ],
                ]],
            ],
        ];
    }
}
