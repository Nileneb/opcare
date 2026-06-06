<?php

namespace App\Domains\Hygiene\Enums;

/**
 * Art eines Erreger-Befunds. § 23 Abs. 4 IfSG verlangt die fortlaufende Aufzeichnung und Bewertung
 * nosokomialer (in der Einrichtung erworbener) Infektionen; eine reine Besiedlung (Kolonisation) ist
 * keine Infektion, wird aber für die Hygienemaßnahmen ebenfalls geführt.
 */
enum BefundArt: string
{
    case Besiedlung = 'besiedlung';
    case Infektion = 'infektion';
    case NosokomialeInfektion = 'nosokomiale_infektion';

    public function label(): string
    {
        return match ($this) {
            self::Besiedlung => 'Besiedlung/Kolonisation',
            self::Infektion => 'Infektion (mitgebracht)',
            self::NosokomialeInfektion => 'nosokomiale Infektion (hier erworben)',
        };
    }

    /** § 23 Abs. 4 IfSG: nosokomiale Infektionen sind bewertungspflichtig. */
    public function bewertungspflichtig(): bool
    {
        return $this === self::NosokomialeInfektion;
    }
}
