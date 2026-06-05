<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Medication\Models\Prescription;

class MedicationStatementMapper
{
    // WHY(Track A Phase 6): ÜLB verlangt medicationReference (separate Medication-Ressource), nicht inline.
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_MedicationStatement_Administration_Instruction|1.0.0';

    public static function id(Prescription $p): string
    {
        return 'medicationstatement-'.$p->id;
    }

    /** @return array<string, mixed> */
    public function map(Prescription $p, string $patientReference, string $medicationReference): array
    {
        $statement = [
            'resourceType' => 'MedicationStatement',
            'id' => self::id($p),
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'status' => $p->ist_aktiv ? 'active' : 'stopped',
            'medicationReference' => ['reference' => $medicationReference],
            'subject' => ['reference' => $patientReference],
        ];

        $period = array_filter([
            'start' => $p->gueltig_von?->toDateString(),
            'end' => $p->gueltig_bis?->toDateString(),
        ]);
        if ($period !== []) {
            $statement['effectivePeriod'] = $period;
        }

        $dosage = $this->dosage($p);
        if ($dosage !== null) {
            $statement['dosage'] = [$dosage];
        }

        return $statement;
    }

    /** @return array<string, mixed>|null */
    private function dosage(Prescription $p): ?array
    {
        if ($p->bei_bedarf) {
            return ['text' => 'Bei Bedarf', 'asNeededBoolean' => true];
        }

        $parts = $p->schedules->flatMap(function ($s) {
            return collect(is_array($s->dosis) ? $s->dosis : [])
                ->filter(fn ($menge) => $menge)
                ->map(fn ($menge, $zeit) => "{$zeit} {$menge}");
        })->all();

        return $parts === [] ? null : ['text' => implode(', ', $parts)];
    }
}
