<?php

namespace App\Domains\Fhir\Epa;

use App\Domains\Medication\Models\MedProduct;

/**
 * MedProduct → gematik ePA `epa-medication` (elektronische Patientenakte, Medikationsliste).
 * Konformität gegen den offiziellen gematik Referenzvalidator (Modul `epa3-medication`). PZN-Coding aus
 * dem ifa/pzn-CodeSystem (falls erfasst) + Freitext; eindeutiger ePA-Identifier als stabiler Hash.
 */
class EpaMedicationMapper
{
    public const PROFILE = 'https://gematik.de/fhir/epa-medication/StructureDefinition/epa-medication|1.3.0';

    private const UNIQUE_ID_SYSTEM = 'https://gematik.de/fhir/epa-medication/sid/epa-medication-unique-identifier';

    public static function id(MedProduct $m): string
    {
        return 'epa-medication-'.$m->id;
    }

    /** @return array<string, mixed> */
    public function map(MedProduct $m): array
    {
        $text = trim($m->name.' '.($m->staerke ?? ''));

        $code = ['text' => $text !== '' ? $text : $m->name];
        if ($m->pzn) {
            $code['coding'] = [['system' => 'http://fhir.de/CodeSystem/ifa/pzn', 'code' => $m->pzn]];
        }

        return [
            'resourceType' => 'Medication',
            'id' => self::id($m),
            'meta' => ['profile' => [self::PROFILE]],
            // WHY(ePA): stabiler, eindeutiger Identifier — SHA-256 über PZN/Name (wie die gematik-Beispiele).
            'identifier' => [[
                'system' => self::UNIQUE_ID_SYSTEM,
                'value' => strtoupper(hash('sha256', ($m->pzn ?? '').'|'.$m->name)),
            ]],
            'code' => $code,
        ];
    }
}
