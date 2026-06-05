<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentAnswer;

/**
 * Assessment (z. B. Barthel-Index) → FHIR-R4-Observations für die ÜLB-Sektion funktionsbeurteilungen.
 * Ein Item-Observation je beantwortetem LOINC-codierten Item + ein Summen-Observation (hasMember).
 * Nur Instrumente mit LOINC-Code werden gemappt (sonst nicht ÜLB-adressierbar → übersprungen).
 */
class AssessmentObservationMapper
{
    public static function totalId(Assessment $a): string
    {
        return 'observation-assessment-'.$a->id;
    }

    public static function itemId(AssessmentAnswer $answer): string
    {
        return 'observation-assessment-'.$answer->assessment_id.'-item-'.$answer->instrument_item_id;
    }

    /** @return array<string, mixed> */
    public function itemObservation(AssessmentAnswer $answer, string $patientReference, string $effective): array
    {
        $item = $answer->instrumentItem;

        return $this->base(self::itemId($answer), $item?->loinc, $item?->label ?? '', $patientReference, $effective, (int) $answer->punkte);
    }

    /**
     * @param  array<int, string>  $memberRefs
     * @return array<string, mixed>
     */
    public function totalObservation(Assessment $a, string $patientReference, string $effective, array $memberRefs): array
    {
        $obs = $this->base(self::totalId($a), $a->instrument?->loinc, $a->instrument?->name ?? 'Assessment', $patientReference, $effective, (int) $a->score);
        if ($a->risk_band) {
            $obs['interpretation'] = [['text' => $a->risk_band->value]];
        }
        if ($memberRefs !== []) {
            $obs['hasMember'] = array_map(fn (string $r) => ['reference' => $r], $memberRefs);
        }

        return $obs;
    }

    /** @return array<string, mixed> */
    private function base(string $id, ?string $loinc, string $display, string $patientReference, string $effective, int $value): array
    {
        $coding = $loinc ? [['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $display]] : [];

        return [
            'resourceType' => 'Observation',
            'id' => $id,
            'status' => 'final',
            'category' => [[
                'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'survey']],
            ]],
            'code' => array_filter(['coding' => $coding ?: null, 'text' => $display]),
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $effective,
            'valueQuantity' => ['value' => $value, 'unit' => 'Punkte'],
        ];
    }
}
