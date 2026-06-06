<?php

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Enums\Bundesland;

/**
 * Leitet das Bundesland aus der Postleitzahl ab (Näherung anhand der PLZ-Leitregion / erste zwei Ziffern).
 * Einige Leitregionen überschreiten Landesgrenzen — das Ergebnis ist daher ein Vorschlag, der manuell
 * korrigierbar bleibt (siehe Heimrecht-Seite). So wird das passende Landesheimgesetz automatisch gewählt,
 * ohne dass der Träger es manuell zuordnen muss.
 */
class BundeslandResolver
{
    public static function fromPlz(?string $plz): ?Bundesland
    {
        if ($plz === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $plz);
        if ($digits === null || strlen($digits) < 2) {
            return null;
        }

        $prefix = (int) substr($digits, 0, 2);

        return match (true) {
            $prefix === 1, $prefix === 2, $prefix === 4, $prefix === 8, $prefix === 9 => Bundesland::SN,
            $prefix === 3 => Bundesland::BB,
            $prefix === 6 => Bundesland::ST,
            $prefix === 7 => Bundesland::TH,
            $prefix === 10, $prefix === 12, $prefix === 13 => Bundesland::BE,
            $prefix === 14, $prefix === 15, $prefix === 16 => Bundesland::BB,
            $prefix === 17, $prefix === 18, $prefix === 19 => Bundesland::MV,
            $prefix === 20, $prefix === 22 => Bundesland::HH,
            $prefix === 23, $prefix === 24, $prefix === 25 => Bundesland::SH,
            $prefix === 21, $prefix === 26, $prefix === 27, $prefix === 29, $prefix === 30, $prefix === 31 => Bundesland::NI,
            $prefix === 28 => Bundesland::HB,
            $prefix === 32, $prefix === 33 => Bundesland::NW,
            $prefix === 34, $prefix === 35, $prefix === 36 => Bundesland::HE,
            $prefix === 37, $prefix === 38 => Bundesland::NI,
            $prefix === 39 => Bundesland::ST,
            $prefix >= 40 && $prefix <= 48 => Bundesland::NW,
            $prefix === 49 => Bundesland::NI,
            $prefix >= 50 && $prefix <= 53 => Bundesland::NW,
            $prefix === 54, $prefix === 55, $prefix === 56 => Bundesland::RP,
            $prefix === 57, $prefix === 58, $prefix === 59 => Bundesland::NW,
            $prefix >= 60 && $prefix <= 65 => Bundesland::HE,
            $prefix === 66 => Bundesland::SL,
            $prefix === 67 => Bundesland::RP,
            $prefix === 68, $prefix === 69 => Bundesland::BW,
            $prefix >= 70 && $prefix <= 79 => Bundesland::BW,
            $prefix >= 80 && $prefix <= 87 => Bundesland::BY,
            $prefix === 88 => Bundesland::BW,
            $prefix >= 89 && $prefix <= 97 => Bundesland::BY,
            $prefix === 98, $prefix === 99 => Bundesland::TH,
            default => null,
        };
    }
}
