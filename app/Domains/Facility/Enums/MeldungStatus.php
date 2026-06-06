<?php

namespace App\Domains\Facility\Enums;

enum MeldungStatus: string
{
    case Offen = 'offen';
    case InArbeit = 'in_arbeit';
    case Erledigt = 'erledigt';

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'offen',
            self::InArbeit => 'in Arbeit',
            self::Erledigt => 'erledigt',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Offen => 'red',
            self::InArbeit => 'amber',
            self::Erledigt => 'green',
        };
    }

    public function offen(): bool
    {
        return $this !== self::Erledigt;
    }
}
