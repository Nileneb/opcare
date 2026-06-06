<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentContact;

/**
 * Mappt An-/Zugehörige (ResidentContact) auf KBV_PR_MIO_ULB_RelatedPerson_Contact_Person für die
 * ÜLB-Sektion patientenAdressbuch. Die Beziehung steht als Freitext in relationship.text — eine codierte
 * relationship.coding bliebe ohne kuratierte Beziehungs-Codeliste eine erfundene Zuordnung (s. INBETRIEBNAHME).
 */
class RelatedPersonMapper
{
    /** @return array<string, mixed> */
    public function build(ResidentContact $contact, string $patientReference, string $base): array
    {
        // KBV_PR_Base_Datatype_Name verlangt name.family (min=1). Der Kontaktname liegt als ein String vor →
        // letztes Token = Nachname, vorangehende = Vornamen.
        $parts = preg_split('/\s+/', trim($contact->name)) ?: [];
        $family = array_pop($parts) ?? $contact->name;
        $name = ['use' => 'official', 'family' => $family, 'text' => $contact->name];
        if ($parts !== []) {
            $name['given'] = $parts;
        }

        $resource = [
            'resourceType' => 'RelatedPerson',
            'id' => 'relatedperson-'.$contact->id,
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_RelatedPerson_Contact_Person|1.0.0']],
            'patient' => ['reference' => $patientReference],
            'name' => [$name],
        ];

        if (($contact->beziehung ?? '') !== '') {
            $resource['relationship'] = [['text' => $contact->beziehung]];
        }
        if (($contact->telefon ?? '') !== '') {
            $resource['telecom'] = [['system' => 'phone', 'value' => $contact->telefon]];
        }

        return $resource;
    }

    public static function ref(ResidentContact $contact, string $base): string
    {
        return $base.'RelatedPerson/relatedperson-'.$contact->id;
    }
}
