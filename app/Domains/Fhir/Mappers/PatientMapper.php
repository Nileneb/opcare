<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\Resident;

class PatientMapper
{
    public static function id(Resident $r): string
    {
        return 'resident-'.$r->id;
    }

    // WHY(Track A Phase 6): ÜLB-Patient-Profil geclaimt — meta.profile (Pflicht) + family/given.
    // KVNR/PID-Identifier-Slices der ÜLB sind geschlossen; ein Custom-System würde scheitern,
    // daher bewusst kein identifier (GKV-KVNR-Mapping ist eine spätere Konformitäts-Iteration).
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Patient|1.0.0';

    /** @return array<string, mixed> */
    public function map(Resident $r): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => self::id($r),
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'name' => [$this->name($r->name)],
            'gender' => match ($r->geschlecht) {
                'm' => 'male',
                'w' => 'female',
                'd' => 'other',
                default => 'unknown',
            },
            'birthDate' => $r->geburtsdatum?->toDateString(),
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
        $name = ['use' => 'official', 'family' => $family];
        if ($parts !== []) {
            $name['given'] = array_values($parts);
        }

        return $name;
    }
}
