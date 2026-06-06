<?php

namespace App\Domains\Masterdata\Enums;

/**
 * Aufgabenkreise der rechtlichen Betreuung (§ 1815 BGB). Die Betreuung wird nur für die konkret
 * angeordneten Kreise bestellt; daran wird die Sicht/Benachrichtigung der Vertretung gegated.
 */
enum Aufgabenkreis: string
{
    case Gesundheitssorge = 'gesundheitssorge';
    case Aufenthaltsbestimmung = 'aufenthaltsbestimmung';
    case Vermoegenssorge = 'vermoegenssorge';
    case Wohnungsangelegenheiten = 'wohnungsangelegenheiten';
    case Behoerdenangelegenheiten = 'behoerdenangelegenheiten';
    case Postangelegenheiten = 'postangelegenheiten';

    public function label(): string
    {
        return match ($this) {
            self::Gesundheitssorge => 'Gesundheitssorge',
            self::Aufenthaltsbestimmung => 'Aufenthaltsbestimmung',
            self::Vermoegenssorge => 'Vermögenssorge',
            self::Wohnungsangelegenheiten => 'Wohnungsangelegenheiten',
            self::Behoerdenangelegenheiten => 'Behördenangelegenheiten',
            self::Postangelegenheiten => 'Postangelegenheiten',
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::Gesundheitssorge => '§ 1815 Abs. 2 Nr. 1, §§ 1827 ff. BGB',
            self::Aufenthaltsbestimmung => '§ 1815 Abs. 2 Nr. 2 BGB',
            self::Wohnungsangelegenheiten => '§ 1815 Abs. 2 Nr. 3, § 1833 BGB',
            self::Vermoegenssorge => '§ 1815 Abs. 1, §§ 1835 ff. BGB',
            self::Behoerdenangelegenheiten => '§ 1815 Abs. 1 BGB',
            self::Postangelegenheiten => '§ 1815 Abs. 2 Nr. 4 BGB',
        };
    }
}
