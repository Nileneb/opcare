<?php

namespace App\Domains\Catering\Enums;

enum ReinigungsIntervall: string
{
    case Taeglich = 'taeglich';
    case Woechentlich = 'woechentlich';
    case ZweiWoechentlich = 'zwei_woechentlich';
    case Monatlich = 'monatlich';
    case Quartalsweise = 'quartalsweise';

    public function label(): string
    {
        return match ($this) {
            self::Taeglich => 'Täglich',
            self::Woechentlich => 'Wöchentlich',
            self::ZweiWoechentlich => 'Zweiwöchentlich',
            self::Monatlich => 'Monatlich',
            self::Quartalsweise => 'Vierteljährlich',
        };
    }

    public function tage(): int
    {
        return match ($this) {
            self::Taeglich => 1,
            self::Woechentlich => 7,
            self::ZweiWoechentlich => 14,
            self::Monatlich => 30,
            self::Quartalsweise => 90,
        };
    }
}
