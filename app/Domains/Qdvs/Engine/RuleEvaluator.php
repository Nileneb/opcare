<?php

namespace App\Domains\Qdvs\Engine;

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Data\ValidationIssue;
use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use Carbon\CarbonImmutable;

class RuleEvaluator
{
    public function __construct(private readonly FieldMap $map) {}

    /**
     * Wertet die kompilierten Regeln gegen ein Paket aus und liefert je Verstoß ein Issue.
     *
     * @param  array<int, CompiledRule>  $rules
     * @return array<int, ValidationIssue>
     */
    public function evaluate(array $rules, QdvsResidentPackage $package, CarbonImmutable $today): array
    {
        $ctx = new EvaluationContext($package, $this->map, $today);

        $issues = [];
        foreach ($rules as $rule) {
            if ($rule->violated($ctx)) {
                $issues[] = new ValidationIssue(
                    pseudonym: $package->pseudonym,
                    feld: $rule->fields[0] ?? $rule->ruleId,
                    meldung: $rule->ruleText,
                    schwere: $rule->schwere,
                );
            }
        }

        return $issues;
    }
}
