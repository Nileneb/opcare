<?php

namespace App\Domains\Catering\Enums;

/** Art eines allgemeinen Essenswunsches eines Bewohners (Vorschlag/Vorliebe für die Küche). */
enum EssenswunschArt: string
{
    case Vorliebe = 'vorliebe';
    case Abneigung = 'abneigung';
    case Hinweis = 'hinweis';

    public function label(): string
    {
        return match ($this) {
            self::Vorliebe => 'Vorliebe',
            self::Abneigung => 'mag nicht',
            self::Hinweis => 'Hinweis',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Vorliebe => 'green',
            self::Abneigung => 'amber',
            self::Hinweis => 'gray',
        };
    }
}
