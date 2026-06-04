<?php

namespace App\Domains\Quality\Enums;

enum EventSeverity: string
{
    case Leicht = 'leicht';
    case Mittel = 'mittel';
    case Schwer = 'schwer';
    case OhneFolgen = 'ohne_folgen';
    case MitFolgen = 'mit_folgen';
}
