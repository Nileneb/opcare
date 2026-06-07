<?php

namespace App\Domains\Voting\Enums;

enum Abstimmungsart: string
{
    case Umfrage = 'umfrage';
    case Wahl = 'wahl';
    case Beschluss = 'beschluss';

    public function label(): string
    {
        return match ($this) {
            self::Umfrage => 'Umfrage',
            self::Wahl => 'Wahl',
            self::Beschluss => 'Beschluss',
        };
    }
}
