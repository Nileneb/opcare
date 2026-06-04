<?php

namespace App\Domains\Qdvs\Engine\Data;

use App\Domains\Qdvs\Engine\Enums\SkipReason;

class EvaluationReport
{
    /**
     * @param  array<string, int>  $skipped  SkipReason->value => Anzahl
     * @param  array<string, array<int, string>>  $skippedRuleIds  SkipReason->value => [ruleId]
     * @param  array<string, int>  $patternUsage  patternKey => Anzahl kompilierter Regeln
     */
    public function __construct(
        public int $total,
        public int $applicable,
        public array $skipped,
        public array $skippedRuleIds,
        public array $patternUsage,
    ) {}

    /**
     * @param  array<int, CompiledRule>  $compiled
     * @param  array<int, array{ruleId: string, reason: SkipReason}>  $skips
     */
    public static function build(array $compiled, array $skips, int $total): self
    {
        $skipped = [];
        $skippedRuleIds = [];
        foreach ($skips as $skip) {
            $reason = $skip['reason'];
            $skipped[$reason->value] = ($skipped[$reason->value] ?? 0) + 1;
            $skippedRuleIds[$reason->value][] = $skip['ruleId'];
        }

        $patternUsage = [];
        foreach ($compiled as $rule) {
            $patternUsage[$rule->patternKey] = ($patternUsage[$rule->patternKey] ?? 0) + 1;
        }

        return new self(
            total: $total,
            applicable: count($compiled),
            skipped: $skipped,
            skippedRuleIds: $skippedRuleIds,
            patternUsage: $patternUsage,
        );
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return [
            'total' => $this->total,
            'applicable' => $this->applicable,
            'skipped' => $this->skipped,
            'patterns' => $this->patternUsage,
        ];
    }
}
