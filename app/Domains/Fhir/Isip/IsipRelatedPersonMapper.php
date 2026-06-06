<?php

namespace App\Domains\Fhir\Isip;

use App\Domains\Masterdata\Models\ResidentContact;

/**
 * ResidentContact → gematik ISiP `ISiPAngehoeriger` (RelatedPerson). Konformität gegen den gematik
 * Referenzvalidator (Modul isip1). Pflicht: patient, name, relationship. Beziehung als Freitext +
 * generischer v3-RoleCode (FAMMEMB), da opcare die Beziehung unstrukturiert erfasst.
 */
class IsipRelatedPersonMapper
{
    public const PROFILE = 'https://gematik.de/fhir/isip/v1/Basismodul/StructureDefinition/ISiPAngehoeriger';

    public static function id(ResidentContact $c): string
    {
        return 'isip-angehoeriger-'.$c->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentContact $c): array
    {
        return [
            'resourceType' => 'RelatedPerson',
            'id' => self::id($c),
            'meta' => ['profile' => [self::PROFILE]],
            'patient' => ['reference' => 'Patient/isip-pflegeempfaenger-'.$c->resident_id],
            'name' => [$this->name($c->name)],
            'relationship' => [[
                'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v3-RoleCode', 'code' => 'FAMMEMB', 'display' => 'family member']],
                'text' => $c->beziehung,
            ]],
        ];
    }

    /**
     * Zerlegt den als ein String gespeicherten Namen in family/given (letztes Token = Nachname).
     *
     * @return array<string, mixed>
     */
    private function name(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $family = array_pop($parts) ?? '';

        return ['use' => 'official', 'family' => $family, 'given' => $parts !== [] ? $parts : ['NN']];
    }
}
