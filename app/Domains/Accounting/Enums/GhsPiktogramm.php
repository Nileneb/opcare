<?php

namespace App\Domains\Accounting\Enums;

enum GhsPiktogramm: string
{
    case GHS01 = 'GHS01';
    case GHS02 = 'GHS02';
    case GHS03 = 'GHS03';
    case GHS04 = 'GHS04';
    case GHS05 = 'GHS05';
    case GHS06 = 'GHS06';
    case GHS07 = 'GHS07';
    case GHS08 = 'GHS08';
    case GHS09 = 'GHS09';

    public function label(): string
    {
        return match ($this) {
            self::GHS01 => 'GHS01 Explosiv',
            self::GHS02 => 'GHS02 Entzündbar',
            self::GHS03 => 'GHS03 Brandfördernd',
            self::GHS04 => 'GHS04 Gas unter Druck',
            self::GHS05 => 'GHS05 Ätzend',
            self::GHS06 => 'GHS06 Giftig',
            self::GHS07 => 'GHS07 Reizend/Gesundheitsschädlich',
            self::GHS08 => 'GHS08 Gesundheitsgefahr',
            self::GHS09 => 'GHS09 Umweltgefährlich',
        };
    }
}
