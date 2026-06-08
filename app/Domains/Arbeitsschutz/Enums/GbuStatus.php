<?php

namespace App\Domains\Arbeitsschutz\Enums;

enum GbuStatus: string
{
    case Entwurf = 'entwurf';
    case Freigegeben = 'freigegeben';
    case Ueberarbeitung = 'ueberarbeitung';

    public function label(): string
    {
        return match ($this) {
            self::Entwurf => 'Entwurf',
            self::Freigegeben => 'Freigegeben',
            self::Ueberarbeitung => 'Überarbeitung',
        };
    }
}
