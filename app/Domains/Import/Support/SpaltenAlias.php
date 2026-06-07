<?php

namespace App\Domains\Import\Support;

use Illuminate\Support\Str;

final class SpaltenAlias
{
    public const ALIASSE = [
        'name' => ['name', 'bezeichnung', 'artikel', 'artikelname', 'artikelbezeichnung'],
        'einheit' => ['einheit', 'einh', 'me', 'mengeneinheit'],
        'abteilung' => ['abteilung', 'bereich'],
        'einkaufspreis' => ['einkaufspreis', 'ek', 'ek-preis', 'preis'],
        'mindestbestand' => ['mindestbestand', 'minbestand', 'meldebestand'],
        'bestand' => ['bestand', 'anfangsbestand', 'menge', 'startbestand'],
        'einstandspreis' => ['einstandspreis', 'wert', 'bewertungspreis'],
        'pg_nummer' => ['pg_nummer', 'pg', 'hmv', 'positionsnummer'],
        'lieferant' => ['lieferant', 'kreditor', 'supplier', 'händler'],
        'charge_nr' => ['charge_nr', 'charge', 'los', 'chargennummer'],
        'mhd' => ['mhd', 'verfall', 'verfallsdatum', 'haltbarkeit'],
    ];

    /**
     * Maps each target field to the first matching original header column, or null if none found.
     *
     * @param  string[]  $header
     * @return array<string, string|null>
     */
    public static function erkenne(array $header): array
    {
        $result = array_fill_keys(array_keys(self::ALIASSE), null);

        foreach (self::ALIASSE as $zielfeld => $aliasse) {
            foreach ($header as $spalte) {
                if (in_array(Str::lower(trim($spalte)), $aliasse, strict: true)) {
                    $result[$zielfeld] = $spalte;
                    break;
                }
            }
        }

        return $result;
    }
}
