<?php

namespace App\Domains\Qdvs\Engine\Enums;

enum RuleStatus: string
{
    case EvaluatedPass = 'evaluated_pass';
    case EvaluatedViolation = 'evaluated_violation';
    case Skipped = 'skipped';
}
