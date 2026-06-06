<?php

namespace App\Domains\Fhir\Isip;

use App\Domains\Identity\Models\Tenant;

/**
 * Tenant → gematik ISiP `IsipOrganization` (Pflegeeinrichtung). Konformität gegen den gematik
 * Referenzvalidator (Modul isip1). Pflicht: identifier (Institutionskennzeichen), type (Einrichtungsart),
 * name. Struktur aus der gematik-Beispielressource verifiziert.
 */
class IsipOrganizationMapper
{
    public const PROFILE = 'https://gematik.de/fhir/isip/v1/Basismodul/StructureDefinition/IsipOrganization';

    public static function id(Tenant $t): string
    {
        return 'isip-organization-'.$t->id;
    }

    /** @return array<string, mixed> */
    public function map(Tenant $t): array
    {
        $ik = $t->ik_nummer ?: '000000000';

        $org = [
            'resourceType' => 'Organization',
            'id' => self::id($t),
            'meta' => ['profile' => [self::PROFILE]],
            'identifier' => [[
                'system' => 'http://fhir.de/sid/arge-ik/iknr',
                'value' => $ik,
            ]],
            'active' => true,
            'type' => [['coding' => [[
                'system' => 'http://snomed.info/sct',
                'code' => '42665001',
                'display' => 'Nursing home (environment)',
            ]]]],
            'name' => $t->name,
        ];

        if ($t->strasse && $t->plz && $t->ort) {
            $org['address'] = [[
                'type' => 'both',
                'line' => [trim($t->strasse.' '.($t->hausnummer ?? ''))],
                'city' => $t->ort,
                'postalCode' => $t->plz,
                'country' => 'DE',
            ]];
        }

        return $org;
    }
}
