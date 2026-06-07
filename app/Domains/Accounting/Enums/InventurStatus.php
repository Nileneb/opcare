<?php

namespace App\Domains\Accounting\Enums;

/**
 * Status einer Inventur-Kampagne: in Zählung (offen) oder abgeschlossen (Differenzen gebucht, Bestandswert
 * eingefroren). Append-only-Charakter — abgeschlossen wird nicht wieder geöffnet.
 */
enum InventurStatus: string
{
    case Offen = 'offen';
    case Abgeschlossen = 'abgeschlossen';

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'offen (in Zählung)',
            self::Abgeschlossen => 'abgeschlossen',
        };
    }
}
