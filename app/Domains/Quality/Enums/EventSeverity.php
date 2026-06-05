<?php

namespace App\Domains\Quality\Enums;

enum EventSeverity: string
{
    case Leicht = 'leicht';
    case Mittel = 'mittel';
    case Schwer = 'schwer';
    case OhneFolgen = 'ohne_folgen';
    case MitFolgen = 'mit_folgen';

    public function label(): string
    {
        return match ($this) {
            self::Leicht => 'leicht',
            self::Mittel => 'mittel',
            self::Schwer => 'schwer',
            self::OhneFolgen => 'ohne Folgen',
            self::MitFolgen => 'mit Folgen',
        };
    }
}
