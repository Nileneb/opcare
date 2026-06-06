<?php

namespace App\Domains\Quality\Enums;

enum GremiumFunktion: string
{
    case Vorsitz = 'vorsitz';
    case Stellvertretung = 'stellvertretung';
    case Schriftfuehrung = 'schriftfuehrung';
    case Mitglied = 'mitglied';

    public function label(): string
    {
        return match ($this) {
            self::Vorsitz => 'Vorsitz',
            self::Stellvertretung => 'Stellvertretung',
            self::Schriftfuehrung => 'Schriftführung',
            self::Mitglied => 'Mitglied',
        };
    }
}
