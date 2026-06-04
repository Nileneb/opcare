<?php

namespace App\Domains\Qdvs\Engine;

use App\Domains\Qdvs\Engine\Contracts\RulePattern;
use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationReport;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Patterns\ConditionalMandatoryPattern;
use App\Domains\Qdvs\Engine\Patterns\DataTypeCheckPattern;
use App\Domains\Qdvs\Engine\Patterns\DateComparisonPattern;
use App\Domains\Qdvs\Engine\Patterns\FutureDatePattern;
use App\Domains\Qdvs\Engine\Patterns\KeyValueCheckPattern;
use App\Domains\Qdvs\Engine\Patterns\ListMandatoryPattern;
use App\Domains\Qdvs\Engine\Patterns\MandatoryFieldPattern;
use App\Domains\Qdvs\Engine\Patterns\QuantorSomePattern;
use App\Domains\Qdvs\Engine\Patterns\ValueRangePattern;
use App\Domains\Qdvs\Engine\Support\AssertNormalizer;
use App\Domains\Qdvs\Engine\Support\FieldMap;

class AssertCompiler
{
    /** @var array<int, RulePattern> */
    private array $patterns;

    public function __construct(
        private readonly AssertNormalizer $normalizer,
        private readonly FieldMap $map,
    ) {
        // Reihenfolge: spezifisch → generisch
        $this->patterns = [
            new ConditionalMandatoryPattern,
            new MandatoryFieldPattern,
            new ListMandatoryPattern,
            new KeyValueCheckPattern,
            new DataTypeCheckPattern,
            new ValueRangePattern,
            new FutureDatePattern,
            new DateComparisonPattern,
            new QuantorSomePattern,
        ];
    }

    public function compile(RawRule $rule): CompiledRule|SkipReason
    {
        $assert = $this->normalizer->normalize($rule->assertTest);

        // WHY(DAS_REGELN): .//resident prüft über alle Datensätze — im Einzelpaket nicht auswertbar
        if (str_contains($assert, './/resident')) {
            return SkipReason::OutOfScopeAggregate;
        }

        foreach ($this->patterns as $pattern) {
            $result = $pattern->tryCompile($assert, $rule, $this->map);
            if ($result !== null) {
                return $result;
            }
        }

        return SkipReason::UnknownPattern;
    }

    /**
     * Kompiliert alle Regeln und liefert die scharf geschalteten plus einen Coverage-Report.
     *
     * @param  array<int, RawRule>  $rules
     * @return array{compiled: array<int, CompiledRule>, report: EvaluationReport}
     */
    public function compileAll(array $rules): array
    {
        $compiled = [];
        $skips = [];
        foreach ($rules as $rule) {
            $result = $this->compile($rule);
            if ($result instanceof CompiledRule) {
                $compiled[] = $result;
            } else {
                // WHY: rule_ids sind nur je Dataset eindeutig (qs_data_mds wiederholt sie) → flache Liste
                $skips[] = ['ruleId' => $rule->dataset.':'.$rule->ruleId, 'reason' => $result];
            }
        }

        return [
            'compiled' => $compiled,
            'report' => EvaluationReport::build($compiled, $skips, count($rules)),
        ];
    }
}
