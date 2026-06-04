<?php

namespace App\Domains\CarePlanning\Enums;

enum SisTopicField: string
{
    case Kognition = 'kognition';
    case Mobilitaet = 'mobilitaet';
    case Krankheitsbezogen = 'krankheitsbezogen';
    case Selbstversorgung = 'selbstversorgung';
    case SozialeBeziehungen = 'soziale_beziehungen';
    case Wohnen = 'wohnen';

    public function label(): string
    {
        return match ($this) {
            self::Kognition => 'Kognition & Kommunikation',
            self::Mobilitaet => 'Mobilität & Beweglichkeit',
            self::Krankheitsbezogen => 'Krankheitsbezogene Anforderungen & Belastungen',
            self::Selbstversorgung => 'Selbstversorgung',
            self::SozialeBeziehungen => 'Leben in sozialen Beziehungen',
            self::Wohnen => 'Wohnen & Häuslichkeit',
        };
    }
}
