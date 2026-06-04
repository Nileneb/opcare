<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Contracts\RulePattern;
use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use Closure;

abstract class AbstractPattern implements RulePattern
{
    /**
     * Liefert UnmappedField, sobald ein referenziertes DAS-Feld nicht auf das opcare-Paket gemappt ist.
     *
     * @param  array<int, string>  $fields
     */
    protected function requireMapped(array $fields, FieldMap $map): ?SkipReason
    {
        foreach ($fields as $f) {
            if (! $map->has($f)) {
                return SkipReason::UnmappedField;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $fields
     * @param  Closure(EvaluationContext): bool  $evaluate
     */
    protected function compiled(RawRule $rule, array $fields, Closure $evaluate): CompiledRule
    {
        return new CompiledRule(
            ruleId: $rule->ruleId,
            ruleText: $rule->ruleText,
            schwere: $rule->schwere(),
            patternKey: $this->key(),
            fields: $fields,
            evaluate: $evaluate,
        );
    }
}
