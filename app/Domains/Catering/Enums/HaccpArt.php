<?php

namespace App\Domains\Catering\Enums;

/** Typ eines HACCP-Messpunkts bestimmt Grenzwert-Richtung (max = Kühlung, min = Heißhaltung). */
enum HaccpArt: string
{
    case Kuehlung = 'kuehlung';
    case Tiefkuehlung = 'tiefkuehlung';
    case Heisshaltung = 'heisshaltung';
    case Ausgabe = 'ausgabe';

    public function label(): string
    {
        return match ($this) {
            self::Kuehlung => 'Kühlung',
            self::Tiefkuehlung => 'Tiefkühlung',
            self::Heisshaltung => 'Heißhaltung',
            self::Ausgabe => 'Warmausgabe',
        };
    }

    /** Grenzwert-Default nach DIN 10508 / Leitlinien (VO (EG) 852/2004 Art. 5). */
    public function grenzwertDefault(): float
    {
        return match ($this) {
            self::Kuehlung => 7.0,
            self::Tiefkuehlung => -18.0,
            self::Heisshaltung => 65.0,
            self::Ausgabe => 65.0,
        };
    }

    /**
     * true → Messwert muss ≤ Grenzwert (Kühlung: zu warm = Abweichung).
     * false → Messwert muss ≥ Grenzwert (Heißhaltung: zu kalt = Abweichung).
     * WHY: Kühlung und TK sind Max-CCPs, Heißhaltung/Ausgabe sind Min-CCPs — DIN 10508.
     */
    public function istMax(): bool
    {
        return match ($this) {
            self::Kuehlung, self::Tiefkuehlung => true,
            self::Heisshaltung, self::Ausgabe => false,
        };
    }
}
