<?php

namespace App\Domains\Personnel\Enums;

enum Geschlecht: string
{
    case Maennlich = 'm';
    case Weiblich = 'w';
    case Divers = 'd';

    public function label(): string
    {
        return match ($this) {
            self::Maennlich => 'männlich',
            self::Weiblich => 'weiblich',
            self::Divers => 'divers',
        };
    }
}
