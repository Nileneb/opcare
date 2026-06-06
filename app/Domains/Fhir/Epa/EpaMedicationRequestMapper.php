<?php

namespace App\Domains\Fhir\Epa;

use App\Domains\Medication\Models\Prescription;

/**
 * Prescription → gematik ePA `epa-medication-request` (Verordnung in der Medikationsliste der ePA).
 * Konformität gegen den gematik Referenzvalidator (Modul epa3-medication). subject als KVNR-Identifier
 * (kvid-10), medication als Referenz auf die epa-medication. Anders als epa-medication-statement (EML)
 * erfordert die Request KEINE E-Rezept-Prozess-ID → aus opcare-Verordnungsdaten erzeugbar.
 */
class EpaMedicationRequestMapper
{
    public const PROFILE = 'https://gematik.de/fhir/epa-medication/StructureDefinition/epa-medication-request|1.3.0';

    public static function id(Prescription $p): string
    {
        return 'epa-medication-request-'.$p->id;
    }

    /** @return array<string, mixed> */
    public function map(Prescription $p, string $kvnr): array
    {
        return [
            'resourceType' => 'MedicationRequest',
            'id' => self::id($p),
            'meta' => ['profile' => [self::PROFILE]],
            'status' => $p->abgesetzt_am !== null ? 'stopped' : 'active',
            'intent' => 'order',
            'medicationReference' => ['reference' => 'Medication/'.EpaMedicationMapper::id($p->medProduct)],
            'subject' => ['identifier' => ['system' => 'http://fhir.de/sid/gkv/kvid-10', 'value' => $kvnr]],
            'authoredOn' => $p->gueltig_von->toDateString(),
            'dispenseRequest' => ['quantity' => ['value' => 1, 'unit' => 'Packung']],
        ];
    }
}
