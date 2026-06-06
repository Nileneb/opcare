<?php

namespace App\Domains\Quality\Enums;

enum BeschwerdeKategorie: string
{
    case Anregung = 'anregung';
    case Beschwerde = 'beschwerde';
    case Lob = 'lob';
    case Gewaltvorfall = 'gewaltvorfall';

    public function label(): string
    {
        return match ($this) {
            self::Anregung => 'Anregung',
            self::Beschwerde => 'Beschwerde',
            self::Lob => 'Lob',
            self::Gewaltvorfall => 'Gewaltvorfall (Gewaltschutz)',
        };
    }

    public function istGewalt(): bool
    {
        return $this === self::Gewaltvorfall;
    }
}
