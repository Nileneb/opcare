<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentAllergy;

/**
 * ResidentAllergy → FHIR R4 AllergyIntolerance, ÜLB-konform (KBV_PR_MIO_ULB_AllergyIntolerance).
 */
class AllergyIntoleranceMapper
{
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_AllergyIntolerance|1.0.0';

    private const TYP = ['allergie' => 'allergy', 'unvertraeglichkeit' => 'intolerance'];

    private const KATEGORIE = ['medikament' => 'medication', 'nahrung' => 'food', 'umwelt' => 'environment', 'biologisch' => 'biologic'];

    private const KRITIKALITAET = ['niedrig' => 'low', 'hoch' => 'high', 'unbekannt' => 'unable-to-assess'];

    public static function id(ResidentAllergy $a): string
    {
        return 'allergyintolerance-'.$a->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentAllergy $a, string $patientReference, string $recorderReference): array
    {
        $resource = [
            'resourceType' => 'AllergyIntolerance',
            'id' => self::id($a),
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'clinicalStatus' => $this->status('http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical', 'active', 'Active'),
            'verificationStatus' => $this->status('http://terminology.hl7.org/CodeSystem/allergyintolerance-verification', 'confirmed', 'Confirmed'),
            'type' => self::TYP[$a->typ] ?? 'allergy',
            'code' => ['text' => $a->substanz],
            'patient' => ['reference' => $patientReference],
            // WHY(ÜLB): recorder ist Pflicht (Practitioner/Organisation der Einrichtung)
            'recorder' => ['reference' => $recorderReference],
        ];

        if (isset(self::KATEGORIE[$a->kategorie])) {
            $resource['category'] = [self::KATEGORIE[$a->kategorie]];
        }
        if (isset(self::KRITIKALITAET[$a->kritikalitaet])) {
            $resource['criticality'] = self::KRITIKALITAET[$a->kritikalitaet];
        }
        // WHY(ÜLB): recordedDate ist verboten (max=0); erfasst_am bleibt nur im opcare-Datensatz.
        if ($a->reaktion) {
            // FHIR: reaction.manifestation ist Pflicht, sobald eine reaction angegeben wird
            $resource['reaction'] = [['manifestation' => [['text' => $a->reaktion]]]];
        }

        return $resource;
    }

    // WHY(ÜLB): KBV verlangt CodeSystem-version; allergyintolerance-clinical/-verification = 1.0.1
    // (vom Validator mit geladener Terminologie vorgegeben).
    /** @return array<string, mixed> */
    private function status(string $system, string $code, string $display): array
    {
        return ['coding' => [['system' => $system, 'version' => '1.0.1', 'code' => $code, 'display' => $display]]];
    }
}
