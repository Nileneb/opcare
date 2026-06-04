<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class DataTypeCheckPattern extends AbstractPattern
{
    // exists(X/@value) and (not(X/@value castable as xs:TYPE) or string-length(xs:string(X/@value)) OP N)
    private const RE = '/^exists\(([A-Z][A-Z0-9]+)\/@value\) and \(not\(\1\/@value castable as (xs:\w+)\) or string-length\(xs:string\(\1\/@value\)\) (>|!=|<|>=|<=) (\d+)\)$/';

    public function key(): string
    {
        return 'DATA_TYPE_CHECK';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m)) {
            return null;
        }

        [$field, $type, $op, $len] = [$m[1], $m[2], $m[3], (int) $m[4]];
        if ($skip = $this->requireMapped([$field], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$field], function (EvaluationContext $ctx) use ($field, $type, $op, $len) {
            $v = $ctx->raw($field);
            if (Xpath::isEmpty($v)) {
                return false;
            }

            $badType = match ($type) {
                'xs:int', 'xs:integer', 'xs:gYear' => ! Xpath::castableAsInt($v),
                'xs:decimal' => ! Xpath::castableAsDecimal($v),
                'xs:date' => ! Xpath::castableAsDate($v),
                default => false,
            };

            return $badType || $this->lengthViolates(mb_strlen((string) $v), $op, $len);
        });
    }

    private function lengthViolates(int $length, string $op, int $n): bool
    {
        return match ($op) {
            '>' => $length > $n,
            '>=' => $length >= $n,
            '<' => $length < $n,
            '<=' => $length <= $n,
            '!=' => $length !== $n,
            default => false,
        };
    }
}
