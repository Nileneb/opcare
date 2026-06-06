<?php

namespace App\Domains\Fhir\Evp;

use App\Domains\Fhir\Isip\IsipPatientMapper;
use App\Domains\Masterdata\Models\Resident;

/**
 * Resident.pflegegrad → GKV-SV EVP `GKVSV_PR_EVP_Pflegegrad` (Elektronischer Versorgungsplan Pflege).
 * Konformität gegen den offiziellen gematik Referenzvalidator (Modul `evp`). Erbt von
 * observation-de-pflegegrad → fixe LOINC 80391-6, value aus ValueSet pflegegrad-de (OPS). Struktur aus
 * der GKV-SV-Beispielressource verifiziert.
 */
class EvpPflegegradMapper
{
    public const PROFILE = 'https://fhir.gkvsv.de/StructureDefinition/GKVSV_PR_EVP_Pflegegrad|1.0';

    private const OPS = 'http://fhir.de/CodeSystem/bfarm/ops';

    /** Pflegegrad 1–5 → [OPS-Code, Display]. */
    private const OPS_BY_GRADE = [
        1 => ['9-984.6', 'Pflegebedürftig nach Pflegegrad 1'],
        2 => ['9-984.7', 'Pflegebedürftig nach Pflegegrad 2'],
        3 => ['9-984.8', 'Pflegebedürftig nach Pflegegrad 3'],
        4 => ['9-984.9', 'Pflegebedürftig nach Pflegegrad 4'],
        5 => ['9-984.a', 'Pflegebedürftig nach Pflegegrad 5'],
    ];

    public static function id(Resident $r): string
    {
        return 'evp-pflegegrad-'.$r->id;
    }

    /** @return array<string, mixed> */
    public function map(Resident $r): array
    {
        $obs = [
            'resourceType' => 'Observation',
            'id' => self::id($r),
            'meta' => ['profile' => [self::PROFILE]],
            'status' => 'final',
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '80391-6', 'display' => 'Pflegegrad']]],
            'subject' => ['reference' => 'Patient/'.IsipPatientMapper::id($r)],
            // WHY(EVP): effectivePeriod ist Pflicht (min 1) — Pflegegrad gilt ab Einzug (Aufnahme).
            'effectivePeriod' => ['start' => ($r->aufnahme_am ?? $r->created_at)?->toDateString()],
        ];

        $grade = $r->pflegegrad;
        if ($grade !== null && isset(self::OPS_BY_GRADE[$grade])) {
            [$code, $display] = self::OPS_BY_GRADE[$grade];
            $obs['valueCodeableConcept'] = [
                'coding' => [['system' => self::OPS, 'version' => '2019', 'code' => $code]],
                'text' => $display,
            ];
        }

        return $obs;
    }
}
