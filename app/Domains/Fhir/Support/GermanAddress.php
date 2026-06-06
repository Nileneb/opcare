<?php

namespace App\Domains\Fhir\Support;

/**
 * Baut eine deutsche Postadresse als FHIR-Address mit den KBV-Pflicht-Strukturextensions
 * (Straße + Hausnummer als ADXP-Slices auf `line`). Geteilt von E-Rezept- und ÜLB-Mappern —
 * beide KBV-Profile (KBV_PR_ERP_*, KBV_PR_MIO_ULB_Organization) verlangen exakt diese Struktur.
 */
final class GermanAddress
{
    /**
     * @return array<int, array<string, mixed>>|null null, wenn keine ausreichenden Adressdaten vorliegen
     */
    public static function kbv(?string $strasse, ?string $hausnummer, ?string $plz, ?string $ort): ?array
    {
        if (! $strasse || ! $plz || ! $ort) {
            return null;
        }

        $line = trim($strasse.' '.($hausnummer ?? ''));
        $extensions = [];
        if ($hausnummer) {
            $extensions[] = ['url' => 'http://hl7.org/fhir/StructureDefinition/iso21090-ADXP-houseNumber', 'valueString' => $hausnummer];
        }
        $extensions[] = ['url' => 'http://hl7.org/fhir/StructureDefinition/iso21090-ADXP-streetName', 'valueString' => $strasse];

        return [[
            'type' => 'both',
            'line' => [$line],
            '_line' => [['extension' => $extensions]],
            'city' => $ort,
            'postalCode' => $plz,
            'country' => 'D',
        ]];
    }
}
