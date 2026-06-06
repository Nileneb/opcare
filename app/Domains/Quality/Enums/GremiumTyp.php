<?php

namespace App\Domains\Quality\Enums;

enum GremiumTyp: string
{
    case Heimbeirat = 'heimbeirat';
    case Angehoerigenbeirat = 'angehoerigenbeirat';
    case Bewohnervertretung = 'bewohnervertretung';
    case Qualitaetszirkel = 'qualitaetszirkel';
    case Arbeitsschutzausschuss = 'arbeitsschutzausschuss';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Heimbeirat => 'Heimbeirat',
            self::Angehoerigenbeirat => 'Angehörigenbeirat',
            self::Bewohnervertretung => 'Bewohnervertretung',
            self::Qualitaetszirkel => 'Qualitätszirkel',
            self::Arbeitsschutzausschuss => 'Arbeitsschutzausschuss (ASA)',
            self::Sonstiges => 'Sonstiges Gremium',
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::Heimbeirat, self::Bewohnervertretung => 'HeimmwV / § 10 WBVG / Landes-WTG',
            self::Angehoerigenbeirat => 'Landes-WTG (Mitwirkung)',
            self::Qualitaetszirkel => '§ 113 SGB XI (QM)',
            self::Arbeitsschutzausschuss => '§ 11 ASiG',
            self::Sonstiges => '—',
        };
    }

    /** Regel-Amtszeit in Monaten (HeimmwV § 8: Heimbeirat zwei Jahre). null = keine feste Wahlperiode. */
    public function standardPeriodeMonate(): ?int
    {
        return match ($this) {
            self::Heimbeirat, self::Bewohnervertretung, self::Angehoerigenbeirat => 24,
            default => null,
        };
    }

    /** Soll-Sitzungstakt in Monaten (ASA: mind. vierteljährlich, § 11 ASiG). null = kein fester Takt. */
    public function standardSitzungIntervallMonate(): ?int
    {
        return match ($this) {
            self::Arbeitsschutzausschuss => 3,
            self::Heimbeirat, self::Qualitaetszirkel => 3,
            default => null,
        };
    }
}
