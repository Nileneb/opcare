<?php

namespace App\Domains\Personnel\Enums;

/** Lohnsteuerklassen I–VI (ELStAM). */
enum Steuerklasse: string
{
    case I = '1';
    case II = '2';
    case III = '3';
    case IV = '4';
    case V = '5';
    case VI = '6';

    public function label(): string
    {
        return 'Steuerklasse '.match ($this) {
            self::I => 'I', self::II => 'II', self::III => 'III',
            self::IV => 'IV', self::V => 'V', self::VI => 'VI',
        };
    }
}
