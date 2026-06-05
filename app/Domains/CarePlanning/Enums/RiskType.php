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
    // WHY(ÜLB funktionsbeurteilungen): Mobilität/Funktion (z. B. Barthel-Index) — kein QualityIndicator,
    // eskaliert daher bewusst nicht (EscalateToQuality::tryFrom('mobilitaet') = null).
    case Mobilitaet = 'mobilitaet';
}
