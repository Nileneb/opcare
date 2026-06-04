<?php

namespace App\Domains\Assessment\Enums;

enum ScaleDirection: string
{
    // WHY: bei Braden bedeutet ein NIEDRIGER Score höheres Risiko (lower_is_worse),
    // bei Sturz-/Schmerzskalen ein HÖHERER Score höheres Risiko (higher_is_worse).
    case LowerIsWorse = 'lower_is_worse';
    case HigherIsWorse = 'higher_is_worse';
}
