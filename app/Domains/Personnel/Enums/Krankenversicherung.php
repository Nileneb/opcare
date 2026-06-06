<?php

namespace App\Domains\Personnel\Enums;

/** Art der Krankenversicherung (für die SV-Meldung relevant). */
enum Krankenversicherung: string
{
    case GesetzlichPflicht = 'gesetzlich_pflicht';
    case GesetzlichFreiwillig = 'gesetzlich_freiwillig';
    case Privat = 'privat';
    case Familienversichert = 'familienversichert';

    public function label(): string
    {
        return match ($this) {
            self::GesetzlichPflicht => 'gesetzlich pflichtversichert',
            self::GesetzlichFreiwillig => 'gesetzlich freiwillig',
            self::Privat => 'privat versichert',
            self::Familienversichert => 'familienversichert',
        };
    }
}
