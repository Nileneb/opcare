<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class MandatoryFieldPattern extends AbstractPattern
{
    // not(exists(X/@value)) or string-length(xs:string(X/@value)) = 0 or xs:string(X/@value) = ''
    private const RE = "/^not\(exists\(([A-Z][A-Z0-9]+)\/@value\)\) or string-length\(xs:string\(\\1\/@value\)\) = 0 or xs:string\(\\1\/@value\) = ''$/";

    public function key(): string
    {
        return 'MANDATORY_FIELD';
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

        return $this->compiled($rule, [$field], fn (EvaluationContext $ctx) => Xpath::isEmpty($ctx->raw($field)));
    }
}
