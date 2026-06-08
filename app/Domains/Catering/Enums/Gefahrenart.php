<?php

namespace App\Domains\Catering\Enums;

/**
 * Gefahrenkategorie der HACCP-Gefahrenanalyse.
 * Norm-Anker: Codex Alimentarius CAC/RCP 1-1969 (HACCP-Prinzip 1), VO (EG) 852/2004 Art. 5,
 * VO (EU) 1169/2011 (LMIV — Allergene als eigene Kategorie).
 */
enum Gefahrenart: string
{
    case Biologisch = 'biologisch';
    case Chemisch = 'chemisch';
    case Physikalisch = 'physikalisch';
    case Allergen = 'allergen';

    public function label(): string
    {
        return match ($this) {
            self::Biologisch => 'Biologisch',
            self::Chemisch => 'Chemisch',
            self::Physikalisch => 'Physikalisch',
            self::Allergen => 'Allergen',
        };
    }

    /** Kurzkennung (B/C/P/A) wie in HACCP-Plänen üblich. */
    public function kuerzel(): string
    {
        return match ($this) {
            self::Biologisch => 'B',
            self::Chemisch => 'C',
            self::Physikalisch => 'P',
            self::Allergen => 'A',
        };
    }

    public function beispiel(): string
    {
        return match ($this) {
            self::Biologisch => 'Salmonellen, Listerien, Noroviren, Schimmel',
            self::Chemisch => 'Reinigungsmittel-Rückstände, Mykotoxine, Schwermetalle',
            self::Physikalisch => 'Glas-/Metallsplitter, Knochen, Verpackungsreste',
            self::Allergen => 'Kreuzkontakt mit Gluten, Nüssen, Milch, Ei',
        };
    }
}
