<?php

namespace App\Domains\Accounting\Enums;

/**
 * Abteilung, der ein Lagerartikel zugeordnet ist — verknüpft die Warenwirtschaft mit der Buchhaltung:
 * der Verbrauch wird auf das Aufwandskonto der jeweiligen Abteilung gebucht.
 */
enum Abteilung: string
{
    case Kueche = 'kueche';
    case Hauswirtschaft = 'hauswirtschaft';
    case Medikation = 'medikation';
    case Haustechnik = 'haustechnik';
    case Pflege = 'pflege';
    case Verwaltung = 'verwaltung';

    public function label(): string
    {
        return match ($this) {
            self::Kueche => 'Küche',
            self::Hauswirtschaft => 'Hauswirtschaft',
            self::Medikation => 'Medikation/Apotheke',
            self::Haustechnik => 'Haustechnik',
            self::Pflege => 'Pflege (Verbrauchsmaterial)',
            self::Verwaltung => 'Verwaltung',
        };
    }

    /** Aufwandskonto-Nummer dieser Abteilung (für die Verbrauchsbuchung). */
    public function aufwandKonto(): string
    {
        return match ($this) {
            self::Kueche => '5400',
            self::Hauswirtschaft => '5410',
            self::Medikation => '5420',
            self::Haustechnik => '5430',
            self::Pflege => '5440',
            self::Verwaltung => '5490',
        };
    }

    public function aufwandName(): string
    {
        return match ($this) {
            self::Kueche => 'Wareneinsatz Küche',
            self::Hauswirtschaft => 'Material Hauswirtschaft',
            self::Medikation => 'Arznei-/Verbandmittel',
            self::Haustechnik => 'Material Haustechnik',
            self::Pflege => 'Pflegeverbrauchsmaterial',
            self::Verwaltung => 'Verwaltungsbedarf',
        };
    }
}
