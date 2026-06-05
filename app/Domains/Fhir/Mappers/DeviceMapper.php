<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentDevice;

/**
 * ResidentDevice → FHIR-R4-Device (type.text + patient) für die ÜLB-Sektion medizinprodukte.
 * Entspricht der ÜLB-Freitext-Variante (Device_Other_Item).
 */
class DeviceMapper
{
    // WHY(Track A Phase 6): ÜLB-Device-Profil (Other_Item) geclaimt. status + note sind im Profil verboten;
    // eine Terminologie-Assoziations-Extension (SNOMED 260787004) ist Pflicht.
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Device_Other_Item|1.0.0';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    public static function id(ResidentDevice $d): string
    {
        return 'device-'.$d->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentDevice $d, string $patientReference): array
    {
        return [
            'resourceType' => 'Device',
            'id' => self::id($d),
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'extension' => [[
                'url' => 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Terminologie_Assoziation',
                'valueCodeableConcept' => ['coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'version' => self::SNOMED_VERSION,
                    'code' => '260787004',
                    'display' => 'Physical object (physical object)',
                ]]],
            ]],
            'type' => ['text' => $d->bezeichnung],
            'patient' => ['reference' => $patientReference],
        ];
    }
}
