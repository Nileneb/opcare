<?php

namespace App\Domains\Accounting\Enums;

enum BestellStatus: string
{
    case Entwurf = 'entwurf';
    case Bestellt = 'bestellt';
    case TeilweiseGeliefert = 'teilweise';
    case Geliefert = 'geliefert';
    case Storniert = 'storniert';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Bestellt => 'Bestellt',
            self::TeilweiseGeliefert => 'Teilweise geliefert',
            self::Geliefert => 'Geliefert',
            self::Storniert => 'Storniert',
        };
    }
}
