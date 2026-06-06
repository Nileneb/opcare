<?php

namespace App\Domains\Scheduling\Enums;

/** Art eines Dienstwunsches (Vorschlagscharakter — nicht bindend, Hilfe für die Dienstplanung). */
enum WunschTyp: string
{
    case Frei = 'frei';
    case Arbeiten = 'arbeiten';
    case NichtVerfuegbar = 'nicht_verfuegbar';

    public function label(): string
    {
        return match ($this) {
            self::Frei => 'möchte frei',
            self::Arbeiten => 'möchte arbeiten',
            self::NichtVerfuegbar => 'nicht verfügbar',
        };
    }

    public function kurz(): string
    {
        return match ($this) {
            self::Frei => 'frei',
            self::Arbeiten => '✓ Wunsch',
            self::NichtVerfuegbar => '✗',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Frei => 'amber',
            self::Arbeiten => 'green',
            self::NichtVerfuegbar => 'red',
        };
    }
}
