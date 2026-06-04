<?php

namespace App\Domains\Qdvs\Engine\Contracts;

use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Support\FieldMap;

interface RulePattern
{
    public function key(): string;

    /**
     * Versucht den normalisierten assert_test zu kompilieren.
     *
     * @return CompiledRule|SkipReason|null CompiledRule = Muster erkannt + alle Felder gemappt;
     *                                      SkipReason::UnmappedField = Muster erkannt, aber ein Feld fehlt;
     *                                      null = dieses Muster passt nicht (nächster Matcher)
     */
    public function tryCompile(string $assert, RawRule $rule, FieldMap $map): CompiledRule|SkipReason|null;
}
