<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\Resident;

/**
 * Resident.pflegegrad → KBV_PR_MIO_ULB_Observation_Care_Level (Pflicht-Sektion "pflegegrad").
 * Pflicht-Extension beantragungsstatus; bei vorhandenem Grad zusätzlich value (OPS) + pflegegradstatus
 * (obs-9/obs-11 koppeln value↔pflegegradstatus). Ist kein Grad erfasst → Status "Pflegegrad_unbekannt",
 * kein value.
 */
class CareLevelMapper
{
    private const STATUS_CS = 'https://fhir.kbv.de/CodeSystem/KBV_CS_MIO_ULB_Application_Status_Care_Level';

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
        return 'care-level-'.$r->id;
    }

    /** @return array<string, mixed> */
    public function build(Resident $r, string $patientReference, string $performerReference, string $date): array
    {
        $grade = $r->pflegegrad;
        $hasGrade = $grade !== null && isset(self::OPS_BY_GRADE[$grade]);

        $obs = [
            'resourceType' => 'Observation',
            'id' => self::id($r),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_Care_Level|1.0.0']],
            'extension' => [$this->applicationStatus($hasGrade)],
            'status' => 'final',
            'code' => ['coding' => [[
                'system' => 'http://loinc.org', 'version' => '2.72',
                'code' => '80391-6', 'display' => 'Level of care [Type]',
            ]]],
            'subject' => ['reference' => $patientReference],
            'effectivePeriod' => ['start' => $date],
            'performer' => [['reference' => $performerReference]],
        ];

        if ($hasGrade) {
            // WHY(obs-9): value (Pflegegrad) erfordert zwingend die pflegegradstatus-Extension.
            $obs['extension'][] = [
                'url' => 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Status_Care_Level',
                'valueCodeableConcept' => $this->statusConcept('Pflegegrad_bewilligt', 'Pflegegrad bewilligt'),
            ];
            [$opsCode, $opsDisplay] = self::OPS_BY_GRADE[$grade];
            $obs['valueCodeableConcept'] = ['coding' => [[
                'system' => self::OPS, 'version' => '2021', 'code' => $opsCode, 'display' => $opsDisplay,
            ]]];
        }

        return $obs;
    }

    /** @return array<string, mixed> */
    private function applicationStatus(bool $hasGrade): array
    {
        [$code, $display] = $hasGrade
            ? ['Beantragung_mit_Pflegegradzuweisung_abgeschlossen', 'Beantragung mit Pflegegradzuweisung abgeschlossen']
            : ['Pflegegrad_unbekannt', 'Pflegegrad unbekannt'];

        return [
            'url' => 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Application_Status',
            'extension' => [[
                'url' => 'antragsstatusPflegegrad',
                'valueCodeableConcept' => $this->statusConcept($code, $display),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function statusConcept(string $code, string $display): array
    {
        return ['coding' => [[
            'system' => self::STATUS_CS, 'version' => '1.0.0', 'code' => $code, 'display' => $display,
        ]]];
    }
}
