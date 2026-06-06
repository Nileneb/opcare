<?php

namespace App\Domains\Quality\Enums;

enum VorgangArt: string
{
    case Notiz = 'notiz';
    case Weiterleitung = 'weiterleitung';
    case Statuswechsel = 'statuswechsel';
    case Stellungnahme = 'stellungnahme';
    case Massnahme = 'massnahme';

    public function label(): string
    {
        return match ($this) {
            self::Notiz => 'Notiz',
            self::Weiterleitung => 'Weiterleitung',
            self::Statuswechsel => 'Statuswechsel',
            self::Stellungnahme => 'Stellungnahme',
            self::Massnahme => 'Maßnahme',
        };
    }
}
