<?php

namespace App\Domains\CarePlanning\Enums;

enum RiskType: string
{
    case Dekubitus = 'dekubitus';
    case Sturz = 'sturz';
    case Schmerz = 'schmerz';
    case Ernaehrung = 'ernaehrung';
    case Inkontinenz = 'inkontinenz';
    case Kontraktur = 'kontraktur';
}
