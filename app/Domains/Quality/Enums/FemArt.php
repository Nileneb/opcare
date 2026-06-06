<?php

namespace App\Domains\Quality\Enums;

/**
 * Art einer freiheitsentziehenden Maßnahme (FEM) im Heim (§ 1831 Abs. 4 BGB).
 */
enum FemArt: string
{
    case Bettgitter = 'bettgitter';
    case Bauchgurt = 'bauchgurt';
    case Stuhlgurt = 'stuhlgurt';
    case AbgeschlosseneTuer = 'abgeschlossene_tuer';
    case Trickschloss = 'trickschloss';
    case Medikamentoes = 'medikamentoes';
    case HilfsmittelWegnahme = 'hilfsmittel_wegnahme';
    case ElektronischeUeberwachung = 'elektronische_ueberwachung';
    case Sonstige = 'sonstige';

    public function label(): string
    {
        return match ($this) {
            self::Bettgitter => 'Bettgitter',
            self::Bauchgurt => 'Bauchgurt (Bett)',
            self::Stuhlgurt => 'Gurt/Fixierung (Stuhl)',
            self::AbgeschlosseneTuer => 'Abgeschlossene Tür/Bereich',
            self::Trickschloss => 'Trickschloss',
            self::Medikamentoes => 'Sedierende Medikation',
            self::HilfsmittelWegnahme => 'Wegnahme von Hilfsmitteln',
            self::ElektronischeUeberwachung => 'Elektronische Aufenthaltsüberwachung',
            self::Sonstige => 'Sonstige',
        };
    }
}
