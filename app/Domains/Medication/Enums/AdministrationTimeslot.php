<?php

namespace App\Domains\Medication\Enums;

enum AdministrationTimeslot: string
{
    case NachtMo = 'nacht_mo';
    case Morgens = 'morgens';
    case Mittags = 'mittags';
    case Nachmittags = 'nachmittags';
    case Abends = 'abends';
    case NachtAb = 'nacht_ab';
    case BeiBedarf = 'bei_bedarf';

    /** @return array<int, self> die 6 planbaren Tageszeiten (ohne Bedarf) */
    public static function scheduled(): array
    {
        return [self::NachtMo, self::Morgens, self::Mittags, self::Nachmittags, self::Abends, self::NachtAb];
    }

    public function label(): string
    {
        return match ($this) {
            self::NachtMo => 'Nacht (früh)', self::Morgens => 'Morgens', self::Mittags => 'Mittags',
            self::Nachmittags => 'Nachmittags', self::Abends => 'Abends', self::NachtAb => 'Nacht (spät)',
            self::BeiBedarf => 'Bei Bedarf',
        };
    }
}
