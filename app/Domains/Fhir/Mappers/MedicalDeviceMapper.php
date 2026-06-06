<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentDevice;
use Illuminate\Support\Collection;

/**
 * Mappt erfasste Medizinprodukte/Hilfsmittel (ResidentDevice) auf die ÜLB-Sektion medizinprodukte:
 * eine Presence-Observation (Observation_Relevant_Information_Medical_Devices) verweist per Has_Member auf
 * je eine DeviceUseStatement→Device-Kette. Basis-Device-Variante: Device.type.text trägt die Bezeichnung;
 * eine SNOMID-codierte type.coding bleibt einer kuratierten Geräte-Codeliste vorbehalten (s. INBETRIEBNAHME).
 */
class MedicalDeviceMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    private const HAS_MEMBER_EXT = 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Reference_Has_Member';

    /**
     * @param  Collection<int, ResidentDevice>  $devices
     * @return array{entries: array<int, array<string, mixed>>, presenceRef: string}|null
     */
    public function build(Collection $devices, int $residentId, string $patientReference, string $performerReference, string $base, string $date): ?array
    {
        if ($devices->isEmpty()) {
            return null;
        }

        $entries = [];
        $statementRefs = [];
        foreach ($devices as $device) {
            $deviceId = 'device-'.$device->id;
            $deviceRef = $base.'Device/'.$deviceId;
            $entries[] = ['fullUrl' => $deviceRef, 'resource' => [
                'resourceType' => 'Device',
                'id' => $deviceId,
                'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Device|1.0.0']],
                'type' => ['text' => $device->bezeichnung],
                'patient' => ['reference' => $patientReference],
            ]];

            $statementId = 'deviceusestatement-'.$device->id;
            $statementRef = $base.'DeviceUseStatement/'.$statementId;
            $statementRefs[] = $statementRef;
            $entries[] = ['fullUrl' => $statementRef, 'resource' => [
                'resourceType' => 'DeviceUseStatement',
                'id' => $statementId,
                'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_DeviceUseStatement|1.0.0']],
                'status' => 'active',
                'subject' => ['reference' => $patientReference],
                'device' => ['reference' => $deviceRef],
            ]];
        }

        $presenceId = 'devices-presence-'.$residentId;
        $presenceRef = $base.'Observation/'.$presenceId;
        $presence = [
            'resourceType' => 'Observation',
            'id' => $presenceId,
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_Relevant_Information_Medical_Devices|1.0.0']],
            'status' => 'final',
            'code' => ['coding' => [$this->coding('408699006:704325000=63653004', 'Device observable (observable entity) : Relative to (attribute) = Biomedical device (physical object)')]],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $date,
            'performer' => [['reference' => $performerReference]],
            'valueCodeableConcept' => ['coding' => [$this->coding('373573001:246090004=404684003:47429007=63653004', 'Clinical finding present (situation) : Associated finding (attribute) = Clinical finding (finding) : Associated with (attribute) = Biomedical device (physical object)')]],
        ];
        foreach ($statementRefs as $ref) {
            $presence['extension'][] = ['url' => self::HAS_MEMBER_EXT, 'valueReference' => ['reference' => $ref]];
        }
        $entries[] = ['fullUrl' => $presenceRef, 'resource' => $presence];

        return ['entries' => $entries, 'presenceRef' => $presenceRef];
    }

    /** @return array<string, string> */
    private function coding(string $code, string $display): array
    {
        return ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => $code, 'display' => $display];
    }
}
