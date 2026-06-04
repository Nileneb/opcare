<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class QuantorSomePattern extends AbstractPattern
{
    // some $v in X/@value satisfies not(xs:string($v) = ('0','1',...))  → mind. ein Wert nicht im Schlüsselset
    private const RE = "/^some \\\$v in ([A-Z][A-Z0-9]+)\/@value satisfies not\(xs:string\(\\\$v\) = \(([^)]*)\)\)$/";

    public function key(): string
    {
        return 'QUANTOR_SOME';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m)) {
            return null;
        }

        $field = $m[1];
        $allowed = Xpath::valueSet($m[2]);
        if ($skip = $this->requireMapped([$field], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$field], function (EvaluationContext $ctx) use ($field, $allowed) {
            $list = $ctx->raw($field);
            if (! is_array($list)) {
                return false;
            }

            foreach ($list as $v) {
                if (Xpath::present($v) && ! in_array((string) $v, $allowed, true)) {
                    return true;
                }
            }

            return false;
        });
    }
}
