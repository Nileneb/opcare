<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\Resident;

class PatientMapper
{
    public static function id(Resident $r): string
    {
        return 'resident-'.$r->id;
    }

    /** @return array<string, mixed> */
    public function map(Resident $r): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => self::id($r),
            'identifier' => [[
                'system' => 'https://opcare.local/fhir/sid/resident',
                'value' => (string) $r->id,
            ]],
            'name' => [['use' => 'official', 'text' => $r->name]],
            'gender' => match ($r->geschlecht) {
                'm' => 'male',
                'w' => 'female',
                'd' => 'other',
                default => 'unknown',
            },
            'birthDate' => $r->geburtsdatum?->toDateString(),
        ];
    }
}
