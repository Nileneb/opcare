<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Assessment\Models\Assessment;

/**
 * Assessment (z. B. Barthel-Index) → KBV_PR_MIO_ULB_Observation_Assessment_Free (Leaf der Sektion
 * funktionsbeurteilungen). Der ICNP-/Scale-SNOMED-Code (code.coding) ist optional → code.text trägt den
 * Instrumentnamen, valueQuantity den Summenscore.
 */
class AssessmentObservationMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    public static function id(Assessment $a): string
    {
        return 'observation-assessment-'.$a->id;
    }

    /** @return array<string, mixed> */
    public function assessmentFree(Assessment $a, string $patientReference, string $performerReference, string $effective): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => self::id($a),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_Assessment_Free|1.0.0']],
            'status' => 'final',
            'category' => [['coding' => [[
                'system' => self::SNOMED, 'version' => self::SNOMED_VERSION,
                'code' => '424836000', 'display' => 'Assessment section (record artifact)',
            ]]]],
            'code' => ['text' => $a->instrument?->name ?? 'Assessment'],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $effective,
            'performer' => [['reference' => $performerReference]],
            'valueQuantity' => ['value' => (int) $a->score, 'unit' => 'Punkte'],
        ];
    }
}
