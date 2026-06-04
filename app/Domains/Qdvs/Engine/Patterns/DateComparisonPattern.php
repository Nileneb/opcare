<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class DateComparisonPattern extends AbstractPattern
{
    // A/@value < B/@value  (auch >, <=, >=)
    private const RE = '/^([A-Z][A-Z0-9]+)\/@value (<|>|<=|>=) ([A-Z][A-Z0-9]+)\/@value$/';

    public function key(): string
    {
        return 'DATE_COMPARISON';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m)) {
            return null;
        }

        [$left, $op, $right] = [$m[1], $m[2], $m[3]];
        if ($skip = $this->requireMapped([$left, $right], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$left, $right], function (EvaluationContext $ctx) use ($left, $op, $right) {
            $a = Xpath::date($ctx->raw($left));
            $b = Xpath::date($ctx->raw($right));
            if ($a === null || $b === null) {
                return false;
            }

            return match ($op) {
                '<' => $a->lessThan($b),
                '>' => $a->greaterThan($b),
                '<=' => $a->lessThanOrEqualTo($b),
                '>=' => $a->greaterThanOrEqualTo($b),
                default => false,
            };
        });
    }
}
