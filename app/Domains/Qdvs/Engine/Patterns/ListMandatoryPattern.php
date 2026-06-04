<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class ListMandatoryPattern extends AbstractPattern
{
    // every $v in X/@value satisfies not(exists($v)) or string-length(xs:string($v)) = 0 or xs:string($v) = ''
    private const RE = "/^every \\\$v in ([A-Z][A-Z0-9]+)\/@value satisfies not\(exists\(\\\$v\)\) or string-length\(xs:string\(\\\$v\)\) = 0 or xs:string\(\\\$v\) = ''$/";

    public function key(): string
    {
        return 'LIST_MANDATORY';
    }

    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null
    {
        if (! preg_match(self::RE, $assert, $m)) {
            return null;
        }

        $field = $m[1];
        if ($skip = $this->requireMapped([$field], $map)) {
            return $skip;
        }

        return $this->compiled($rule, [$field], function (EvaluationContext $ctx) use ($field) {
            $list = $ctx->raw($field);
            if (! is_array($list) || $list === []) {
                return true;
            }

            foreach ($list as $v) {
                if (Xpath::present($v)) {
                    return false;
                }
            }

            return true;
        });
    }
}
