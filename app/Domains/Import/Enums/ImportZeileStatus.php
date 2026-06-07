<?php

namespace App\Domains\Import\Enums;

enum ImportZeileStatus: string
{
    case Vorgeschlagen = 'vorgeschlagen';
    case Importiert = 'importiert';
    case Uebersprungen = 'uebersprungen';

    public function label(): string
    {
        return match ($this) {
            self::Vorgeschlagen => 'Vorgeschlagen',
            self::Importiert => 'Importiert',
            self::Uebersprungen => 'Übersprungen',
        };
    }
}
