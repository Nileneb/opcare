<?php

namespace App\Domains\Scheduling\Enums;

enum AbwesenheitTyp: string
{
    case Krank = 'krank';
    case Urlaub = 'urlaub';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Krank => 'Krankmeldung',
            self::Urlaub => 'Urlaub',
            self::Sonstiges => 'Sonstige Abwesenheit',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Krank => 'red',
            self::Urlaub => 'green',
            self::Sonstiges => 'gray',
        };
    }
}
