<?php

namespace App\Domains\SocialCare\Enums;

/**
 * Art des Betreuungs-/Aktivierungsangebots (§ 43b SGB XI, QPR QB3). Deckt die üblichen Angebotsformen der
 * zusätzlichen Betreuung ab.
 */
enum BetreuungsArt: string
{
    case Gedaechtnistraining = 'gedaechtnistraining';
    case Bewegung = 'bewegung';
    case Kreativ = 'kreativ';
    case Musik = 'musik';
    case Spaziergang = 'spaziergang';
    case Biografiearbeit = 'biografiearbeit';
    case Religion = 'religion';
    case Hauswirtschaftlich = 'hauswirtschaftlich';
    case Fest = 'fest';
    case Einzelbetreuung = 'einzelbetreuung';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Gedaechtnistraining => 'Gedächtnistraining',
            self::Bewegung => 'Bewegung/Gymnastik',
            self::Kreativ => 'Kreatives (Basteln/Malen)',
            self::Musik => 'Musik/Singen',
            self::Spaziergang => 'Spaziergang/Ausflug',
            self::Biografiearbeit => 'Biografiearbeit',
            self::Religion => 'Religion/Gottesdienst',
            self::Hauswirtschaftlich => 'Hauswirtschaftliches (Kochen/Backen)',
            self::Fest => 'Fest/Feier',
            self::Einzelbetreuung => 'Einzelbetreuung',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
