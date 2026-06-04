<?php

namespace App\Domains\Medication\Enums;

enum AdministrationStatus: string
{
    case Geplant = 'geplant';
    case Gegeben = 'gegeben';
    case Abgelehnt = 'abgelehnt';
    case Ausgelassen = 'ausgelassen';
}
