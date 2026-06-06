<?php

namespace App\Domains\SocialCare\Enums;

/**
 * Handlungsfeld der Prävention in stationären Pflegeeinrichtungen nach dem GKV-Leitfaden Prävention
 * (28.09.2023, § 5 SGB XI). Maßnahmen dieser Felder werden von der Pflegekasse mitfinanziert.
 */
enum Handlungsfeld: string
{
    case Ernaehrung = 'ernaehrung';
    case Bewegung = 'bewegung';
    case Kognition = 'kognition';
    case Psychosozial = 'psychosozial';
    case Gewaltpraevention = 'gewaltpraevention';

    public function label(): string
    {
        return match ($this) {
            self::Ernaehrung => 'Ernährung',
            self::Bewegung => 'Körperliche Aktivität',
            self::Kognition => 'Kognitive Ressourcen',
            self::Psychosozial => 'Psychosoziale Gesundheit',
            self::Gewaltpraevention => 'Prävention von Gewalt',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Ernaehrung => 'green',
            self::Bewegung => 'green',
            self::Kognition => 'amber',
            self::Psychosozial => 'amber',
            self::Gewaltpraevention => 'red',
        };
    }
}
