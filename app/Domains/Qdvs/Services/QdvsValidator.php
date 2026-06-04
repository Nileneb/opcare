<?php

namespace App\Domains\Qdvs\Services;

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Data\ValidationIssue;
use App\Domains\Qdvs\Engine\AssertCompiler;
use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationReport;
use App\Domains\Qdvs\Engine\QdvsRuleRepository;
use App\Domains\Qdvs\Engine\RuleEvaluator;
use Carbon\CarbonImmutable;

/**
 * Fassade über die datengetriebene DAS-Regel-Engine. Signatur bleibt stabil
 * (Caller: BuildQdvsExport), erweitert um report() für die Coverage-Sichtbarkeit.
 */
class QdvsValidator
{
    // WHY(DAS_REGELN): OPCare exportiert die bewohnerbezogene vollstationäre Erhebung → Dataset qs_data.
    // qs_data_mds/facility/commentation sind andere Abgabearten und würden sonst feldgleiche Regeln
    // doppelt feuern lassen.
    private const DATASET = 'qs_data';

    /** @var array<int, CompiledRule>|null */
    private ?array $compiled = null;

    private ?EvaluationReport $report = null;

    public function __construct(
        private readonly QdvsRuleRepository $repository,
        private readonly AssertCompiler $compiler,
        private readonly RuleEvaluator $evaluator,
    ) {}

    /**
     * @param  array<int, QdvsResidentPackage>  $packages
     * @return array<int, ValidationIssue>
     */
    public function validate(array $packages): array
    {
        $rules = $this->rules();
        $today = CarbonImmutable::now();

        $issues = [];
        foreach ($packages as $package) {
            $issues = [
                ...$issues,
                ...$this->evaluator->evaluate($rules, $package, $today),
                ...$this->nativeIssues($package),
            ];
        }

        return $issues;
    }

    /** @param array<int, ValidationIssue> $issues */
    public function hatBlockierendeFehler(array $issues): bool
    {
        return collect($issues)->contains(fn (ValidationIssue $i) => $i->schwere === 'fehler');
    }

    public function report(): ?EvaluationReport
    {
        $this->rules();

        return $this->report;
    }

    /** @return array<int, CompiledRule> */
    private function rules(): array
    {
        if ($this->compiled === null) {
            ['compiled' => $this->compiled, 'report' => $this->report] = $this->compiler->compileAll($this->repository->forDataset(self::DATASET));
        }

        return $this->compiled;
    }

    /**
     * OPCare-eigene Regeln, die das DAS-Vokabular nicht abdeckt.
     *
     * @return array<int, ValidationIssue>
     */
    private function nativeIssues(QdvsResidentPackage $p): array
    {
        $issues = [];

        // WHY(DAS_REGELN): DAS-Feld 7 prüft nur „Pflegegrad vorhanden? 0/1"; das Fehlen fängt die
        // DAS-Mandatory-Regel. Die fachliche 1–5-Spanne ist OPCare-spezifisch.
        if ($p->pflegegrad !== null && ($p->pflegegrad < 1 || $p->pflegegrad > 5)) {
            $issues[] = new ValidationIssue($p->pseudonym, 'pflegegrad', 'Pflegegrad ungültig (1–5).', 'fehler');
        }

        if (! in_array($p->geschlecht, ['m', 'w', 'd'], true)) {
            $issues[] = new ValidationIssue($p->pseudonym, 'geschlecht', 'Geschlecht fehlt/ungültig.', 'fehler');
        }

        // WHY(DAS_REGELN): DAS-DIAGNOSEN ist codiert (≠ ICD), daher als native Warnung statt DAS-Regel
        if (empty($p->icd_codes)) {
            $issues[] = new ValidationIssue($p->pseudonym, 'icd_codes', 'Keine Diagnose hinterlegt.', 'warnung');
        }

        return $issues;
    }
}
