<?php

namespace App\Domains\Quality\Enums;

enum MitgliedArt: string
{
    case Bewohner = 'bewohner';
    case Angehoerige = 'angehoerige';
    case Mitarbeiter = 'mitarbeiter';
    case Leitung = 'leitung';
    case Extern = 'extern';
    case Betriebsarzt = 'betriebsarzt';
    case Sifa = 'sifa';

    public function label(): string
    {
        return match ($this) {
            self::Bewohner => 'Bewohner:in',
            self::Angehoerige => 'Angehörige:r',
            self::Mitarbeiter => 'Mitarbeiter:in',
            self::Leitung => 'Leitung',
            self::Extern => 'Extern',
            self::Betriebsarzt => 'Betriebsarzt',
            self::Sifa => 'Fachkraft f. Arbeitssicherheit',
        };
    }
}
