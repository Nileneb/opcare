<?php

namespace App\Domains\Catering\Enums;

enum GefahrenanalyseStatus: string
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
