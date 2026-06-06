<?php

namespace App\Domains\Fhir\Mappers;

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
    public function build(string $einrichtung, string $base): array
    {
        $org = [
            'resourceType' => 'Organization',
            'id' => self::ORG_ID,
            'meta' => ['profile' => [self::ORG_PROFILE]],
            'name' => $einrichtung !== '' ? $einrichtung : 'Pflegeeinrichtung',
        ];
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
