<?php

namespace App\Domains\Personnel\Enums;

enum Familienstand: string
{
    case Ledig = 'ledig';
    case Verheiratet = 'verheiratet';
    case EingetragenePartnerschaft = 'eingetragene_partnerschaft';
    case Geschieden = 'geschieden';
    case Verwitwet = 'verwitwet';
    case GetrenntLebend = 'getrennt_lebend';

    public function label(): string
    {
        return match ($this) {
            self::Ledig => 'ledig',
            self::Verheiratet => 'verheiratet',
            self::EingetragenePartnerschaft => 'eingetragene Lebenspartnerschaft',
            self::Geschieden => 'geschieden',
            self::Verwitwet => 'verwitwet',
            self::GetrenntLebend => 'getrennt lebend',
        };
    }
}
