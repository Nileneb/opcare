<?php

namespace App\Domains\Arbeitsschutz\Enums;

enum Massnahmentyp: string
{
    case Technisch = 'technisch';
    case Organisatorisch = 'organisatorisch';
    case Personenbezogen = 'personenbezogen';

    public function label(): string
    {
        return match ($this) {
            self::Technisch => 'Technisch',
            self::Organisatorisch => 'Organisatorisch',
            self::Personenbezogen => 'Personenbezogen',
        };
    }

    /** § 4 ArbSchG TOP-Hierarchie: kleinerer Rang = vorrangig. */
    public function rang(): int
    {
        return match ($this) {
            self::Technisch => 1,
            self::Organisatorisch => 2,
            self::Personenbezogen => 3,
        };
    }
}
