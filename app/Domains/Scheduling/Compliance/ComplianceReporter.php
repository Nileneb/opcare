<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Scheduling\Compliance\Data\ComplianceFinding;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Collection;

/**
 * Einstiegspunkt für die Arbeitszeit-Konformität: lässt den WorkingHoursAnalyzer laufen und reichert jeden
 * Befund mit einer ggf. dokumentierten § 14-Begründung an. Ein begründeter Verstoß bleibt ein Verstoß,
 * trägt aber den nachvollziehbaren Grund (z. B. ausbleibende Nachfolgekraft).
 */
class ComplianceReporter
{
    public function __construct(private readonly WorkingHoursAnalyzer $analyzer) {}

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments  mit user + shift geladen
     * @param  Collection<int, ComplianceRule>  $rules
     * @param  Collection<int, ComplianceJustification>  $justifications
     * @return array<int, ComplianceFinding>
     */
    public function findings(Collection $assignments, Collection $rules, Collection $justifications): array
    {
        $findings = $this->analyzer->analyze($assignments, $rules);
        if ($justifications->isEmpty()) {
            return $findings;
        }

        $byKey = $justifications->keyBy(fn (ComplianceJustification $j) => $j->matchKey());
        foreach ($findings as $finding) {
            foreach ($finding->dates as $datum) {
                $match = $byKey->get($finding->ruleKey.'|'.$finding->userId.'|'.$datum);
                if ($match !== null) {
                    $finding->begruendung = $match->grund;
                    $finding->begruendetVon = $match->begruender->name;
                    break;
                }
            }
        }

        return $findings;
    }
}
