<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\CarePlanning\Models\CareMeasure;

/**
 * CareMeasure → KBV_PR_MIO_ULB_Procedure_Nursing_Measures (Composition-Sektion "pflegerischeMassnahme").
 * Die ICNP-SNOMED-Codierung (code.coding) ist optional; opcare-Maßnahmen sind katalog-/freitextbasiert →
 * code.text trägt Themenfeld + Beschreibung.
 */
class ProcedureMapper
{
    public static function id(CareMeasure $m): string
    {
        return 'procedure-'.$m->id;
    }

    /** @return array<string, mixed> */
    public function map(CareMeasure $m, string $patientReference): array
    {
        return [
            'resourceType' => 'Procedure',
            'id' => self::id($m),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Procedure_Nursing_Measures|1.0.0']],
            'status' => 'completed',
            'code' => ['text' => trim($m->themenfeld->value.': '.$m->beschreibung)],
            'subject' => ['reference' => $patientReference],
        ];
    }
}
