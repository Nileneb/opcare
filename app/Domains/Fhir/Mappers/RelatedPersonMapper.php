<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentContact;

/**
 * ResidentContact → FHIR-R4-RelatedPerson für die ÜLB-Sektionen benachrichtigungVonAn-undZugehoerigen
 * und pflegeDurchAngehoerige.
 */
class RelatedPersonMapper
{
    public static function id(ResidentContact $c): string
    {
        return 'relatedperson-'.$c->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentContact $c, string $patientReference): array
    {
        $resource = [
            'resourceType' => 'RelatedPerson',
            'id' => self::id($c),
            'patient' => ['reference' => $patientReference],
            'name' => [['text' => $c->name]],
        ];

        if ($c->beziehung) {
            $resource['relationship'] = [['text' => $c->beziehung]];
        }
        if ($c->telefon) {
            $resource['telecom'] = [['system' => 'phone', 'value' => $c->telefon]];
        }

        return $resource;
    }
}
