<?php

namespace App\Domains\Scheduling\Compliance\Data;

use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use Spatie\LaravelData\Data;

/**
 * Befund einer ergonomischen Schichtplan-Regel (Empfehlung, kein ArbZG-Verstoß).
 */
class QualityFinding extends Data
{
    public function __construct(
        public int $userId,
        public string $userName,
        public string $ruleKey,
        public ViolationSeverity $severity,
        public string $label,
        public string $message,
        public string $quelle,
    ) {}
}
