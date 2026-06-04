<?php

namespace App\Domains\Qdvs\Engine\Data;

use Closure;

class CompiledRule
{
    /**
     * @param  array<int, string>  $fields  referenzierte DAS-Felder
     * @param  Closure(EvaluationContext): bool  $evaluate  true = Fehlerbedingung trifft zu = Verstoß
     */
    public function __construct(
        public string $ruleId,
        public string $ruleText,
        public string $schwere,
        public string $patternKey,
        public array $fields,
        public Closure $evaluate,
    ) {}

    public function violated(EvaluationContext $ctx): bool
    {
        return ($this->evaluate)($ctx);
    }
}
