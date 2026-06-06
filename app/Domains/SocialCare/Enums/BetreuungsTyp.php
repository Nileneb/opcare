<?php

namespace App\Domains\SocialCare\Enums;

enum BetreuungsTyp: string
{
    case Gruppe = 'gruppe';
    case Einzel = 'einzel';

    public function label(): string
    {
        return match ($this) {
            self::Gruppe => 'Gruppenangebot',
            self::Einzel => 'Einzelbetreuung',
        };
    }
}
