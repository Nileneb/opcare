<?php

namespace App\Domains\Fhir\Isip;

use App\Domains\Masterdata\Models\Physician;

/**
 * Physician (Arzt) → gematik ISiP `ISiPPersonImGesundheitswesen` (Practitioner). Konformität gegen den
 * gematik Referenzvalidator (Modul isip1). Pflicht: name (use/family/given).
 */
class IsipPractitionerMapper
{
    public const PROFILE = 'https://gematik.de/fhir/isip/v1/Basismodul/StructureDefinition/ISiPPersonImGesundheitswesen';

    public static function id(Physician $p): string
    {
        return 'isip-person-'.$p->id;
    }

    /** @return array<string, mixed> */
    public function map(Physician $p): array
    {
        $parts = preg_split('/\s+/', trim($p->name)) ?: [];
        $family = array_pop($parts) ?? '';

        return [
            'resourceType' => 'Practitioner',
            'id' => self::id($p),
            'meta' => ['profile' => [self::PROFILE]],
            // WHY(ISiK): Identifier ist Pflicht (min 1); opcare erfasst keine LANR → institutioneller Identifier.
            'identifier' => [[
                'system' => 'https://opcare.local/sid/arzt',
                'value' => (string) $p->id,
            ]],
            'name' => [[
                'use' => 'official',
                'text' => $p->name,
                'family' => $family,
                'given' => $parts !== [] ? $parts : ['NN'],
            ]],
        ];
    }
}
