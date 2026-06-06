<?php

namespace App\Domains\Personnel\Enums;

enum BetriebsbetreuungArt: string
{
    case Betriebsarzt = 'betriebsarzt';
    case Sifa = 'sifa';

    public function label(): string
    {
        return match ($this) {
            self::Betriebsarzt => 'Betriebsarzt',
            self::Sifa => 'Fachkraft für Arbeitssicherheit (Sifa)',
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::Betriebsarzt => '§ 2 ASiG / DGUV V2',
            self::Sifa => '§§ 5, 6 ASiG / DGUV V2',
        };
    }
}
