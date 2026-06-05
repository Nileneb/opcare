<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentStatusObservation;
use App\Domains\Masterdata\Support\StatusObservationCatalog;

/**
 * ResidentStatusObservation → FHIR-R4-Observation (SNOMED-codiert oder Freitext) für die ÜLB-Sektionen
 * Bewusstsein/Kontinenz/Ernährung/Atmung. Code + Wert-Codes stammen aus dem StatusObservationCatalog.
 */
class StatusObservationMapper
{
    public static function id(ResidentStatusObservation $o): string
    {
        return 'observation-status-'.$o->id;
    }

    /** @return array<string, mixed>|null */
    public function map(ResidentStatusObservation $o, string $patientReference, string $effective): ?array
    {
        $def = StatusObservationCatalog::get($o->typ);
        if ($def === null) {
            return null;
        }

        $obs = [
            'resourceType' => 'Observation',
            'id' => self::id($o),
            'status' => 'final',
            'category' => [[
                'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'survey']],
            ]],
            'code' => [
                'coding' => [['system' => StatusObservationCatalog::SNOMED, 'code' => $def['code'][0], 'display' => $def['code'][1]]],
                'text' => $def['label'],
            ],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $effective,
        ];

        if (($def['kind'] ?? 'coded') === 'coded' && $o->wert_code) {
            $label = $def['options'][$o->wert_code] ?? $o->wert_code;
            $obs['valueCodeableConcept'] = [
                'coding' => [['system' => StatusObservationCatalog::SNOMED, 'code' => $o->wert_code, 'display' => $label]],
                'text' => $label,
            ];
        } else {
            $obs['valueString'] = (string) $o->wert_text;
        }

        return $obs;
    }

    public function section(ResidentStatusObservation $o): string
    {
        return StatusObservationCatalog::get($o->typ)['section'] ?? 'Pflegerische Einschätzungen';
    }
}
