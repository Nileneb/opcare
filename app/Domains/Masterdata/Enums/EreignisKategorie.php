<?php

namespace App\Domains\Masterdata\Enums;

/**
 * Wesentliche Bewohner-Ereignisse, bei denen die Vertretung ein Beteiligungs-/Informationsrecht hat.
 * Jede Kategorie bestimmt, welche Aufgabenkreise (§ 1815 BGB) benachrichtigt werden müssen.
 */
enum EreignisKategorie: string
{
    case MdBegutachtung = 'md_begutachtung';
    case HeilbehandlungEinwilligung = 'heilbehandlung_einwilligung';
    case AerztlicheMassnahme = 'aerztliche_massnahme';
    case Krankenhaus = 'krankenhaus';
    case Heimvertrag = 'heimvertrag';
    case Posteingang = 'posteingang';
    case Sterbefall = 'sterbefall';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::MdBegutachtung => 'MD-Begutachtung (Pflegegrad)',
            self::HeilbehandlungEinwilligung => 'Heilbehandlung — Einwilligung nötig',
            self::AerztlicheMassnahme => 'Ärztliche Maßnahme / FEM',
            self::Krankenhaus => 'Krankenhaus-Verlegung / Notfall',
            self::Heimvertrag => 'Heimvertrag / Entgelt / Wohnung',
            self::Posteingang => 'Posteingang (Behörde, Wahlunterlagen)',
            self::Sterbefall => 'Sterbefall',
            self::Sonstiges => 'Sonstiges',
        };
    }

    public function rechtsbasis(): string
    {
        return match ($this) {
            self::MdBegutachtung => '§ 18 SGB XI, § 1821 BGB',
            self::HeilbehandlungEinwilligung => '§ 1827 BGB',
            self::AerztlicheMassnahme => '§§ 1829/1831/1832 BGB',
            self::Krankenhaus => '§ 1821 BGB',
            self::Heimvertrag => '§§ 1833/1835 BGB, WBVG',
            self::Posteingang => '§ 1815 Abs. 2 Nr. 4 BGB',
            self::Sterbefall, self::Sonstiges => '§ 1821 BGB',
        };
    }

    /**
     * Aufgabenkreise, deren Inhaber bei diesem Ereignis ein Recht auf Beteiligung/Information haben.
     * Leeres Array = alle aktiven Vertretungen sind zu informieren (z. B. Sterbefall).
     *
     * @return array<int, Aufgabenkreis>
     */
    public function erforderlicheAufgabenkreise(): array
    {
        return match ($this) {
            self::MdBegutachtung, self::Krankenhaus => [
                Aufgabenkreis::Gesundheitssorge,
                Aufgabenkreis::Aufenthaltsbestimmung,
            ],
            self::HeilbehandlungEinwilligung, self::AerztlicheMassnahme => [
                Aufgabenkreis::Gesundheitssorge,
            ],
            self::Heimvertrag => [
                Aufgabenkreis::Wohnungsangelegenheiten,
                Aufgabenkreis::Vermoegenssorge,
            ],
            self::Posteingang => [
                Aufgabenkreis::Postangelegenheiten,
            ],
            self::Sterbefall, self::Sonstiges => [],
        };
    }
}
