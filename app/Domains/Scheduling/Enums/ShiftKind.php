<?php

namespace App\Domains\Scheduling\Enums;

enum ShiftKind: string
{
    case Frueh = 'frueh';
    case Spaet = 'spaet';
    case Nacht = 'nacht';
    case Zwischendienst = 'zwischendienst';

    public function label(): string
    {
        return match ($this) {
            self::Frueh => 'Frühdienst',
            self::Spaet => 'Spätdienst',
            self::Nacht => 'Nachtdienst',
            self::Zwischendienst => 'Zwischendienst',
        };
    }
}
