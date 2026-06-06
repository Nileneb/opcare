<?php

namespace App\Domains\Quality\Enums;

enum BeschwerdeQuelle: string
{
    case Bewohner = 'bewohner';
    case Angehoerige = 'angehoerige';
    case Mitarbeiter = 'mitarbeiter';
    case Extern = 'extern';

    public function label(): string
    {
        return match ($this) {
            self::Bewohner => 'Bewohner:in',
            self::Angehoerige => 'Angehörige:r',
            self::Mitarbeiter => 'Mitarbeiter:in',
            self::Extern => 'Extern',
        };
    }
}
