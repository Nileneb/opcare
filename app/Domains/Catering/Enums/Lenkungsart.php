<?php

namespace App\Domains\Catering\Enums;

/**
 * Art einer Lenkungsmaßnahme (control measure) zur Beherrschung einer Lebensmittelgefahr.
 * Norm-Anker: Codex Alimentarius (HACCP-Prinzipien 2/3), VO (EG) 852/2004 Art. 5;
 * Basishygiene = Präventivprogramm (PRP/Basishygiene-Anforderungen Anhang II).
 */
enum Lenkungsart: string
{
    case Ccp = 'ccp';
    case Prozesslenkung = 'prozesslenkung';
    case Basishygiene = 'basishygiene';

    public function label(): string
    {
        return match ($this) {
            self::Ccp => 'CCP-Lenkung (überwachter Grenzwert)',
            self::Prozesslenkung => 'Operative Prozesslenkung',
            self::Basishygiene => 'Basishygiene / Präventivprogramm',
        };
    }

    /** Rang in der HACCP-Lenkungslogik: CCP (1) vor operativer Lenkung (2) vor Basishygiene (3). */
    public function rang(): int
    {
        return match ($this) {
            self::Ccp => 1,
            self::Prozesslenkung => 2,
            self::Basishygiene => 3,
        };
    }
}
