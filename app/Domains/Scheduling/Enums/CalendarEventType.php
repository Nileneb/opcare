<?php

namespace App\Domains\Scheduling\Enums;

enum CalendarEventType: string
{
    case Arzttermin = 'arzttermin';
    case Massnahme = 'massnahme';
    case Therapie = 'therapie';
    case Besuch = 'besuch';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Arzttermin => 'Arzttermin',
            self::Massnahme => 'Pflegemaßnahme',
            self::Therapie => 'Therapie',
            self::Besuch => 'Besuch',
            self::Intern => 'Interner Termin',
        };
    }
}
