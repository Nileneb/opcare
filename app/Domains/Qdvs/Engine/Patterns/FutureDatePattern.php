<?php

namespace App\Domains\Qdvs\Engine\Patterns;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use App\Domains\Qdvs\Engine\Support\Xpath;

class FutureDatePattern extends AbstractPattern
{
    // X/@value > current-date()
    private const RE = '/^([A-Z][A-Z0-9]+)\/@value > current-date\(\)$/';

    public function key(): string
    {
        return 'FUTURE_DATE';
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
            $d = Xpath::date($ctx->raw($field));

            return $d !== null && $d->greaterThan($ctx->today);
        });
    }
}
