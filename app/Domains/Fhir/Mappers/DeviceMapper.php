<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentDevice;

/**
 * ResidentDevice → FHIR-R4-Device (type.text + patient) für die ÜLB-Sektion medizinprodukte.
 * Entspricht der ÜLB-Freitext-Variante (Device_Other_Item).
 */
class DeviceMapper
{
    public static function id(ResidentDevice $d): string
    {
        return 'device-'.$d->id;
    }

    /** @return array<string, mixed> */
    public function map(ResidentDevice $d, string $patientReference): array
    {
        $device = [
            'resourceType' => 'Device',
            'id' => self::id($d),
            'status' => 'active',
            'type' => ['text' => $d->bezeichnung],
            'patient' => ['reference' => $patientReference],
        ];

        if ($d->hinweis) {
            $device['note'] = [['text' => $d->hinweis]];
        }

        return $device;
    }
}
