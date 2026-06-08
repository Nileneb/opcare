<?php

namespace App\Domains\Communication\Enums;

enum KonversationTyp: string
{
    case Direkt = 'Direkt';
    case Gruppe = 'Gruppe';
    case Station = 'Station';
    case Ankuendigung = 'Ankuendigung';

    public function label(): string
    {
        return match ($this) {
            self::Direkt => 'Direktnachricht',
            self::Gruppe => 'Gruppe',
            self::Station => 'Stationskanal',
            self::Ankuendigung => 'Ankündigungen',
        };
    }
}
