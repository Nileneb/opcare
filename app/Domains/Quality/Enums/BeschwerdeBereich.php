<?php

namespace App\Domains\Quality\Enums;

enum BeschwerdeBereich: string
{
    case Pflege = 'pflege';
    case Betreuung = 'betreuung';
    case Hauswirtschaft = 'hauswirtschaft';
    case Kueche = 'kueche';
    case Technik = 'technik';
    case Verwaltung = 'verwaltung';
    case Leitung = 'leitung';

    public function label(): string
    {
        return match ($this) {
            self::Pflege => 'Pflege',
            self::Betreuung => 'Soziale Betreuung',
            self::Hauswirtschaft => 'Hauswirtschaft',
            self::Kueche => 'Küche',
            self::Technik => 'Haustechnik',
            self::Verwaltung => 'Verwaltung',
            self::Leitung => 'Leitung',
        };
    }

    /**
     * Rollen, deren Inhaber:innen eine an diesen Bereich weitergeleitete Beschwerde sehen/bearbeiten dürfen.
     *
     * @return array<int, string>
     */
    public function rollen(): array
    {
        return match ($this) {
            self::Pflege => ['pflegefachkraft', 'pflegehilfskraft'],
            self::Betreuung => ['betreuungskraft'],
            self::Hauswirtschaft => ['kueche'],
            self::Kueche => ['kueche'],
            self::Technik => ['haustechnik'],
            self::Verwaltung, self::Leitung => ['admin'],
        };
    }
}
