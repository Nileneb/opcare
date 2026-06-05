<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Medication\Models\Prescription;

/**
 * Prescription → FHIR-R4-Medication (KBV_PR_MIO_ULB_Medication), referenziert vom MedicationStatement.
 * Code als Freitext (Präparatname + Stärke) — PZN/ATC-Codings verlangen KBV-Versionen und sind eine
 * spätere Verfeinerung; status ist im Profil verboten.
 */
class MedicationMapper
{
    public const ULB_PROFILE = 'https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Medication|1.0.0';

    public static function id(Prescription $p): string
    {
        return 'medication-'.$p->id;
    }

    /** @return array<string, mixed> */
    public function map(Prescription $p): array
    {
        return [
            'resourceType' => 'Medication',
            'id' => self::id($p),
            'meta' => ['profile' => [self::ULB_PROFILE]],
            'code' => ['text' => $this->text($p->medProduct)],
        ];
    }

    private function text(?object $product): string
    {
        if (! $product) {
            return 'Unbekanntes Präparat';
        }

        $text = (string) $product->name;
        if ($product->staerke && ! str_contains($text, (string) $product->staerke)) {
            $text .= ' '.$product->staerke;
        }

        return trim($text);
    }
}
