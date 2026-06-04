<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class ValueRangePattern extends AbstractPattern
{
    // X/@value < MIN or X/@value > MAX  (X optional in xs:int(xs:string(...)) gewrappt;
    // MAX optional als year-from-date(current-date()))
    private const RE = '/^(?:xs:int\(xs:string\()?([A-Z][A-Z0-9]+)\/@value(?:\)\))? (<|<=) (\S+) or (?:xs:int\(xs:string\()?([A-Z][A-Z0-9]+)\/@value(?:\)\))? (>|>=) (\S+)$/';

    public function key(): string
    {
        return 'VALUE_RANGE';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m) || $m[1] !== $m[4]) {
            return null;
        }

        $field = $m[1];
        [$loOp, $lo, $hiOp, $hi] = [$m[2], $m[3], $m[5], $m[6]];
        if ($skip = $this->requireMapped([$field], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$field], function (EvaluationContext $ctx) use ($field, $loOp, $lo, $hiOp, $hi) {
            $n = Xpath::number($ctx->raw($field));
            if ($n === null) {
                return false; // Typfehler fängt DataTypeCheck ab
            }

            $min = $this->bound($lo, $ctx);
            $max = $this->bound($hi, $ctx);
            $below = $loOp === '<=' ? $n <= $min : $n < $min;
            $above = $hiOp === '>=' ? $n >= $max : $n > $max;

            return $below || $above;
        });
    }

    private function bound(string $token, EvaluationContext $ctx): float
    {
        // erscheint roh oder als xs:int(xs:string(year-from-date(current-date())))
        if (str_contains($token, 'year-from-date(current-date())')) {
            return (float) $ctx->today->year;
        }

        return (float) $token;
    }
}
