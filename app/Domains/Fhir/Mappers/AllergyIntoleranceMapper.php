<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentAllergy;

/**
 * ResidentAllergy → FHIR R4 AllergyIntolerance. Vorstufe Richtung ÜLB-MIO-Sektion
 * allergienUndUnvertraeglichkeiten (KBV_PR_MIO_ULB_AllergyIntolerance).
 */
class AllergyIntoleranceMapper
{
    private const TYP = ['allergie' => 'allergy', 'unvertraeglichkeit' => 'intolerance'];

    private const KATEGORIE = ['medikament' => 'medication', 'nahrung' => 'food', 'umwelt' => 'environment', 'biologisch' => 'biologic'];

    private const KRITIKALITAET = ['niedrig' => 'low', 'hoch' => 'high', 'unbekannt' => 'unable-to-assess'];

    public static function id(ResidentAllergy $a): string
    {
        return 'allergyintolerance-'.$a->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentAllergy $a, string $patientReference): array
    {
        $resource = [
            'resourceType' => 'AllergyIntolerance',
            'id' => self::id($a),
            'clinicalStatus' => $this->status('http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical', 'active'),
            'verificationStatus' => $this->status('http://terminology.hl7.org/CodeSystem/allergyintolerance-verification', 'confirmed'),
            'type' => self::TYP[$a->typ] ?? 'allergy',
            'code' => ['text' => $a->substanz],
            'patient' => ['reference' => $patientReference],
        ];

        if (isset(self::KATEGORIE[$a->kategorie])) {
            $resource['category'] = [self::KATEGORIE[$a->kategorie]];
        }
        if (isset(self::KRITIKALITAET[$a->kritikalitaet])) {
            $resource['criticality'] = self::KRITIKALITAET[$a->kritikalitaet];
        }
        if ($a->erfasst_am) {
            $resource['recordedDate'] = $a->erfasst_am->toDateString();
        }
        if ($a->reaktion) {
            // FHIR: reaction.manifestation ist Pflicht, sobald eine reaction angegeben wird
            $resource['reaction'] = [['manifestation' => [['text' => $a->reaktion]]]];
        }

        return $resource;
    }

    /** @return array<string, mixed> */
    private function status(string $system, string $code): array
    {
        return ['coding' => [['system' => $system, 'code' => $code]]];
    }
}
