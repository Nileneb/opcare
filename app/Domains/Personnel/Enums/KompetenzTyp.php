<?php

namespace App\Domains\Personnel\Enums;

/** Art einer Kompetenz im Skill-Baum. */
enum KompetenzTyp: string
{
    case Grundberuf = 'grundberuf';
    case Weiterbildung = 'weiterbildung';
    case InterneSchulung = 'interne_schulung';

    public function label(): string
    {
        return match ($this) {
            self::Grundberuf => 'Grundberuf',
            self::Weiterbildung => 'Weiterbildung',
            self::InterneSchulung => 'Interne Schulung / Einweisung',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Grundberuf => 'green',
            self::Weiterbildung => 'amber',
            self::InterneSchulung => 'gray',
        };
    }
}
