<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class ConditionalMandatoryPattern extends AbstractPattern
{
    // A/@value = N and not(exists(B/@value))   |   A/@value = (set) and not(exists(B/@value))
    private const RE = '/^([A-Z][A-Z0-9]+)\/@value = (\d+|\([^)]*\)) and not\(exists\(([A-Z][A-Z0-9]+)\/@value\)\)$/';

    public function key(): string
    {
        return 'CONDITIONAL_MANDATORY';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m)) {
            return null;
        }

        [$trigger, $rawSet, $required] = [$m[1], $m[2], $m[3]];
        $triggerValues = Xpath::valueSet($rawSet);
        if ($skip = $this->requireMapped([$trigger, $required], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$trigger, $required], function (EvaluationContext $ctx) use ($trigger, $triggerValues, $required) {
            $t = $ctx->raw($trigger);

            return Xpath::present($t)
                && in_array((string) $t, $triggerValues, true)
                && Xpath::isEmpty($ctx->raw($required));
        });
    }
}
