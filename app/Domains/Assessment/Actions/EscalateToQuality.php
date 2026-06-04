<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;

class EscalateToQuality
{
    // WHY: bei kritischem Risiko ein Controlling-Ereignis dokumentieren.
    // tryFrom funktioniert direkt: RiskType-Werte (dekubitus/sturz/schmerz) sind
    // identisch mit den QualityIndicator-Werten — kein explizites Match nötig.
    // EventSeverity::Schwer für kritisches Risiko (Hoch/SehrHoch) gewählt.
    public function handle(Assessment $assessment): ?CareEvent
    {
        $assessment->loadMissing('instrument');

        if (! $assessment->risk_band?->istKritisch()) {
            return null;
        }

        $indicator = QualityIndicator::tryFrom($assessment->instrument->risk_type->value);
        if (! $indicator) {
            return null;
        }

        return CareEvent::create([
            'resident_id' => $assessment->resident_id,
            'indicator' => $indicator,
            'datum' => $assessment->durchgefuehrt_am?->toDateString() ?? now()->toDateString(),
            'severity' => EventSeverity::Schwer,
            'details' => [
                'quelle' => 'assessment',
                'instrument' => $assessment->instrument->name,
                'score' => $assessment->score,
                'band' => $assessment->risk_band->value,
            ],
            'reported_by' => $assessment->created_by,
        ]);
    }
}
