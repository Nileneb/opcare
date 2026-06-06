<?php

namespace App\Domains\Personnel\Enums;

enum Beschaeftigungsart: string
{
    case Vollzeit = 'vollzeit';
    case Teilzeit = 'teilzeit';
    case Minijob = 'minijob';
    case Midijob = 'midijob';
    case Ausbildung = 'ausbildung';
    case Aushilfe = 'aushilfe';
    case FsjBufdi = 'fsj_bufdi';

    public function label(): string
    {
        return match ($this) {
            self::Vollzeit => 'Vollzeit',
            self::Teilzeit => 'Teilzeit',
            self::Minijob => 'Minijob (geringfügig)',
            self::Midijob => 'Midijob (Übergangsbereich)',
            self::Ausbildung => 'Ausbildung',
            self::Aushilfe => 'kurzfristige Aushilfe',
            self::FsjBufdi => 'FSJ / Bundesfreiwilligendienst',
        };
    }
}
