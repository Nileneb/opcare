<?php

namespace App\Domains\Brandschutz\Enums;

enum BrandschutzordnungTeil: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Teil A — Aushang',
            self::B => 'Teil B — Beschäftigte',
            self::C => 'Teil C — Brandschutzbeauftragte',
        };
    }

    public function zielgruppe(): string
    {
        return match ($this) {
            self::A => 'Alle (Aushang)',
            self::B => 'Beschäftigte ohne bes. Brandschutzaufgaben',
            self::C => 'Personen mit bes. Brandschutzaufgaben',
        };
    }
}
