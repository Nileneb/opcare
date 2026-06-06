<?php

namespace App\Domains\Quality\Enums;

enum BeschwerdeStatus: string
{
    case Eingegangen = 'eingegangen';
    case InBearbeitung = 'in_bearbeitung';
    case Weitergeleitet = 'weitergeleitet';
    case Erledigt = 'erledigt';
    case Abgelehnt = 'abgelehnt';

    public function label(): string
    {
        return match ($this) {
            self::Eingegangen => 'Eingegangen',
            self::InBearbeitung => 'In Bearbeitung',
            self::Weitergeleitet => 'Weitergeleitet',
            self::Erledigt => 'Erledigt',
            self::Abgelehnt => 'Abgelehnt',
        };
    }

    public function offen(): bool
    {
        return ! in_array($this, [self::Erledigt, self::Abgelehnt], true);
    }
}
