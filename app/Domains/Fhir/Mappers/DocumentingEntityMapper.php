<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Fhir\Support\GermanAddress;
use App\Domains\Identity\Models\Tenant;

/**
 * Baut die dokumentierende Einheit der Pflegeüberleitung als FHIR-Kette
 * Organization ← PractitionerRole → Practitioner (alle ÜLB-konform). Diese wird genau einmal pro
 * Bundle erzeugt und als recorder/performer/author der übrigen Ressourcen referenziert — KBV-MIO-Profile
 * verlangen durchgängig eine Pflicht-Referenz auf einen Dokumentierenden.
 */
class DocumentingEntityMapper
{
    public const ORG_ID = 'org-einrichtung';

    public const PRACTITIONER_ID = 'practitioner-pflegedoku';

    public const ROLE_ID = 'practitionerrole-pflegedoku';

    private const ORG_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Organization|1.0.0';

    private const PRACTITIONER_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Practitioner|1.0.0';

    private const ROLE_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_PractitionerRole|1.0.0';

    /**
     * @return array{entries: array<int, array<string, mixed>>, recorderReference: string, practitionerReference: string}
     */
    public function build(Tenant $tenant, string $base): array
    {
        $org = [
            'resourceType' => 'Organization',
            'id' => self::ORG_ID,
            'meta' => ['profile' => [self::ORG_PROFILE]],
            'name' => $tenant->name !== '' ? $tenant->name : 'Pflegeeinrichtung',
        ];

        if ($tenant->ik_nummer) {
            // IKNR (de.basisprofil identifier-iknr): system+value. Den ÜLB-Slice "Institutionskennzeichen"
            // NICHT formal treffen — er fixiert type.coding.display auf 'Organisations-ID', was am
            // Core-CodeSystem v2-0203 (display 'Organization identifier') scheitern würde (KBV/Core-Konflikt).
            $org['identifier'] = [[
                'system' => 'http://fhir.de/sid/arge-ik/iknr',
                'value' => $tenant->ik_nummer,
            ]];
        }

        $address = GermanAddress::kbv($tenant->strasse, $tenant->hausnummer, $tenant->plz, $tenant->ort);
        if ($address !== null) {
            $org['address'] = $address;
        }
        $practitioner = [
            'resourceType' => 'Practitioner',
            'id' => self::PRACTITIONER_ID,
            'meta' => ['profile' => [self::PRACTITIONER_PROFILE]],
            'name' => [['use' => 'official', 'family' => 'Pflegedokumentation']],
        ];
        $role = [
            'resourceType' => 'PractitionerRole',
            'id' => self::ROLE_ID,
            'meta' => ['profile' => [self::ROLE_PROFILE]],
            'practitioner' => ['reference' => $base.'Practitioner/'.self::PRACTITIONER_ID],
            'organization' => ['reference' => $base.'Organization/'.self::ORG_ID],
        ];

        return [
            'entries' => [
                ['fullUrl' => $base.'Organization/'.self::ORG_ID, 'resource' => $org],
                ['fullUrl' => $base.'Practitioner/'.self::PRACTITIONER_ID, 'resource' => $practitioner],
                ['fullUrl' => $base.'PractitionerRole/'.self::ROLE_ID, 'resource' => $role],
            ],
            'recorderReference' => $base.'PractitionerRole/'.self::ROLE_ID,
            'practitionerReference' => $base.'Practitioner/'.self::PRACTITIONER_ID,
        ];
    }
}
