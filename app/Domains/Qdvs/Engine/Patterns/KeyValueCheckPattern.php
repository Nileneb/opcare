<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class KeyValueCheckPattern extends AbstractPattern
{
    // exists(X/@value) and not(xs:string(X/@value) = ('0','1',...))
    private const RE = '/^exists\(([A-Z][A-Z0-9]+)\/@value\) and not\(xs:string\(\1\/@value\) = \(([^)]*)\)\)$/';

    public function key(): string
    {
        return 'KEY_VALUE_CHECK';
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
            $v = $ctx->raw($field);

            return Xpath::present($v) && ! in_array((string) $v, $allowed, true);
        });
    }
}
