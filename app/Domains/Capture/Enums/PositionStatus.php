<?php

namespace App\Domains\Capture\Enums;

enum PositionStatus: string
{
    case Vorgeschlagen = 'vorgeschlagen';
    case Bestaetigt = 'bestaetigt';
    case Verworfen = 'verworfen';

    public function label(): string
    {
        return match ($this) {
            self::Vorgeschlagen => 'vorgeschlagen',
            self::Bestaetigt => 'bestätigt',
            self::Verworfen => 'verworfen',
        };
    }
}
