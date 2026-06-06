<?php

namespace App\Domains\Catering\Enums;

enum Mahlzeit: string
{
    case Fruehstueck = 'fruehstueck';
    case Mittag = 'mittag';
    case Kaffee = 'kaffee';
    case Abend = 'abend';

    public function label(): string
    {
        return match ($this) {
            self::Fruehstueck => 'Frühstück',
            self::Mittag => 'Mittagessen',
            self::Kaffee => 'Kaffee/Zwischenmahlzeit',
            self::Abend => 'Abendessen',
        };
    }

    public function sort(): int
    {
        return match ($this) {
            self::Fruehstueck => 1,
            self::Mittag => 2,
            self::Kaffee => 3,
            self::Abend => 4,
        };
    }
}
