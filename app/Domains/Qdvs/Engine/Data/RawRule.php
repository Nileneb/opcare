<?php

namespace App\Domains\Qdvs\Engine\Data;

use Spatie\LaravelData\Data;

class RawRule extends Data
{
    public function __construct(
        public string $dataset,
        public string $ruleId,
        public string $assertTest,
        public string $ruleText,
        // 'ERROR' | 'WARNING' (Rohwert aus der DAS-CSV)
        public string $ruleType,
    ) {}

    // WHY(DAS_REGELN): ERROR blockt die Abgabe, WARNING erlaubt sie — mappt auf ValidationIssue::schwere
    public function schwere(): string
    {
        return $this->ruleType === 'WARNING' ? 'warnung' : 'fehler';
    }
}
