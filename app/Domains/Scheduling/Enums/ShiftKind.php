<?php

namespace App\Domains\Scheduling\Enums;

enum ShiftKind: string
{
    case Frueh = 'frueh';
    case Spaet = 'spaet';
    case Nacht = 'nacht';
    case Zwischendienst = 'zwischendienst';
    case Spitzendienst = 'spitzendienst';

    public function label(): string
    {
        return match ($this) {
            self::Frueh => 'Frühdienst',
            self::Spaet => 'Spätdienst',
            self::Nacht => 'Nachtdienst',
            self::Zwischendienst => 'Zwischendienst',
            self::Spitzendienst => 'Spitzendienst',
        };
    }

    /** Kurzer, gezielter Dienst für Bedarfsspitzen (Mahlzeiten/Grundpflege) — kein Vollschicht-Rang. */
    public function istSpitze(): bool
    {
        return $this === self::Spitzendienst;
    }
}
