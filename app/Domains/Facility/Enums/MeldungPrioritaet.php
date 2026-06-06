<?php

namespace App\Domains\Facility\Enums;

enum MeldungPrioritaet: string
{
    case Niedrig = 'niedrig';
    case Mittel = 'mittel';
    case Hoch = 'hoch';
    case Dringend = 'dringend';

    public function label(): string
    {
        return match ($this) {
            self::Niedrig => 'niedrig',
            self::Mittel => 'mittel',
            self::Hoch => 'hoch',
            self::Dringend => 'dringend (Gefahr/Ausfall)',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Niedrig => 'gray',
            self::Mittel => 'gray',
            self::Hoch => 'amber',
            self::Dringend => 'red',
        };
    }
}
