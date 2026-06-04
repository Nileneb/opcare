<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentOption;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Support\RiskBandResolver;
use App\Domains\Assessment\Support\ScoreCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConductAssessment
{
    public function __construct(
        private ScoreCalculator $calculator = new ScoreCalculator,
        private RiskBandResolver $resolver = new RiskBandResolver,
    ) {}

    public function handle(AssessmentInputData $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $instrument = Instrument::with('items')->findOrFail($data->instrument_id);

            // Punkte der gewählten Optionen laden (nur Optionen, die zu Items dieses Instruments gehören)
            $erlaubteItems = $instrument->items->pluck('id')->all();
            $optionen = AssessmentOption::whereIn('id', array_values($data->answers))
                ->whereIn('instrument_item_id', $erlaubteItems)
                ->get()->keyBy('id');

            $punkte = [];
            foreach ($data->answers as $itemId => $optionId) {
                $option = $optionen->get($optionId);
                if ($option && (int) $option->instrument_item_id === (int) $itemId) {
                    $punkte[$itemId] = $option->punkte;
                }
            }

            $score = $this->calculator->sum(array_values($punkte));
            $band = $this->resolver->resolve($score, $instrument->risk_bands, $instrument->direction);

            $durchgefuehrt = Carbon::parse($data->durchgefuehrt_am ?? now()->toDateString());

            $assessment = Assessment::create([
                'resident_id' => $data->resident_id,
                'instrument_id' => $instrument->id,
                'score' => $score,
                'risk_band' => $band,
                'durchgefuehrt_am' => $durchgefuehrt->toDateString(),
                'faellig_am' => $durchgefuehrt->copy()->addDays($instrument->intervall_tage)->toDateString(),
                'notiz' => $data->notiz,
                'created_by' => $data->created_by,
            ]);

            foreach ($punkte as $itemId => $p) {
                $assessment->answers()->create([
                    'instrument_item_id' => $itemId,
                    'assessment_option_id' => $data->answers[$itemId],
                    'punkte' => $p,
                ]);
            }

            return $assessment->fresh('answers');
        });
    }
}
