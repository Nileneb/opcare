<?php

namespace App\Domains\Voting\Enums;

enum Elektorat: string
{
    case Bewohner = 'bewohner';
    case Mitarbeitende = 'mitarbeitende';
    case Gremium = 'gremium';

    public function label(): string
    {
        return match ($this) {
            self::Bewohner => 'Bewohner',
            self::Mitarbeitende => 'Mitarbeitende',
            self::Gremium => 'Gremium',
        };
    }
}
