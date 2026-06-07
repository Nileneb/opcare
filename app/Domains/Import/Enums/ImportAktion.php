<?php

namespace App\Domains\Import\Enums;

enum ImportAktion: string
{
    case Anlegen = 'anlegen';
    case Mergen = 'mergen';
    case Ueberspringen = 'ueberspringen';

    public function label(): string
    {
        return match ($this) {
            self::Anlegen => 'Anlegen',
            self::Mergen => 'Mergen',
            self::Ueberspringen => 'Überspringen',
        };
    }
}
