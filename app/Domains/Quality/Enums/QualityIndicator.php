<?php

namespace App\Domains\Quality\Enums;

// WHY: Pragmatische QS-Indikatoren der stationären Pflege. Das exakte Mapping auf die
// offiziellen QDVS-Ergebnisindikatoren erfolgt in Plan 7 (QDVS-Export).
enum QualityIndicator: string
{
    case Sturz = 'sturz';
    case Dekubitus = 'dekubitus';
    case Gewichtsverlust = 'gewichtsverlust';
    case Schmerz = 'schmerz';
    case Inkontinenz = 'inkontinenz';
    case Fem = 'fem';
    case Wunde = 'wunde';
    case Mangelernaehrung = 'mangelernaehrung';

    public function label(): string
    {
        return match ($this) {
            self::Sturz => 'Sturz',
            self::Dekubitus => 'Dekubitus (neu erworben)',
            self::Gewichtsverlust => 'Unbeabsichtigter Gewichtsverlust',
            self::Schmerz => 'Schmerz',
            self::Inkontinenz => 'Harninkontinenz',
            self::Fem => 'Freiheitsentziehende Maßnahme',
            self::Wunde => 'Chronische Wunde',
            self::Mangelernaehrung => 'Mangelernährungsrisiko',
        };
    }
}
