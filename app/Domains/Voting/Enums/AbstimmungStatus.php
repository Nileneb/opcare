<?php

namespace App\Domains\Voting\Enums;

enum AbstimmungStatus: string
{
    case Entwurf = 'entwurf';
    case Offen = 'offen';
    case Geschlossen = 'geschlossen';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Offen => 'Offen',
            self::Geschlossen => 'Geschlossen',
        };
    }
}
