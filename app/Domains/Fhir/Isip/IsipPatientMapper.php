<?php

namespace App\Domains\Fhir\Isip;

use App\Domains\Masterdata\Models\Resident;

/**
 * Resident → gematik ISiP `ISiPPflegeempfaenger` (Informationstechnische Systeme in der Pflege, v1).
 * Konformität gegen den offiziellen gematik Referenzvalidator (Modul `isip1`). ISiPPflegeempfaenger
 * leitet von ISiKPatient ab → Pflicht: identifier (Patientennummer), name (use/family/given), gender,
 * birthDate. Codes/Struktur aus der gematik-Beispielressource verifiziert.
 */
class IsipPatientMapper
{
    public const PROFILE = 'https://gematik.de/fhir/isip/v1/Basismodul/StructureDefinition/ISiPPflegeempfaenger';

    /** Institutions-internes NamingSystem für die Patientennummer (Pflicht-Identifier-Slice). */
    public const PATIENTENNUMMER_SYSTEM = 'https://opcare.local/sid/pflegeempfaenger';

    public static function id(Resident $r): string
    {
        return 'isip-pflegeempfaenger-'.$r->id;
    }

    /** @return array<string, mixed> */
    public function map(Resident $r): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => self::id($r),
            'meta' => ['profile' => [self::PROFILE]],
            'identifier' => [[
                'use' => 'usual',
                'type' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'MR']]],
                'system' => self::PATIENTENNUMMER_SYSTEM,
                'value' => (string) $r->id,
            ]],
            'name' => [$this->name($r->name)],
            'gender' => match ($r->geschlecht) {
                'm' => 'male',
                'w' => 'female',
                'd' => 'other',
                default => 'unknown',
            },
            'birthDate' => $r->geburtsdatum->toDateString(),
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

        // WHY(ISiK): name.given ist Pflicht (min=1) — „NN" (nomen nescio) als Platzhalter, falls kein
        // Vorname erfasst ist, statt eine ungültige Ressource zu erzeugen.
        $given = $parts !== [] ? $parts : ['NN'];

        return ['use' => 'official', 'family' => $family, 'given' => $given];
    }
}
