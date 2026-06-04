<?php

namespace App\Domains\CarePlanning\Enums;

enum Shift: string
{
    case Frueh = 'frueh';
    case Spaet = 'spaet';
    case Nacht = 'nacht';
}
