<?php

namespace App\Domains\Assessment\Support;

use App\Domains\Assessment\Enums\RiskBand;
use App\Domains\Assessment\Enums\ScaleDirection;

class RiskBandResolver
{
    /**
     * @param  array<int, array{band:string, min:?int, max:?int}>  $bands
     *
     * Findet das Band, dessen [min, max]-Intervall den Score einschließt (null = offenes Ende).
     * Die `direction` ist dokumentarisch (die Schwellen sind bereits skalenkonform definiert);
     * sie wird zur Validierung herangezogen, falls kein Band passt.
     */
    public function resolve(int $score, array $bands, ScaleDirection $direction): RiskBand
    {
        foreach ($bands as $band) {
            $min = $band['min'];
            $max = $band['max'];
            if (($min === null || $score >= $min) && ($max === null || $score <= $max)) {
                return RiskBand::from($band['band']);
            }
        }

        // WHY: kein definiertes Band getroffen → konservativ das schlechtere Ende annehmen.
        return $direction === ScaleDirection::LowerIsWorse ? RiskBand::SehrHoch : RiskBand::Hoch;
    }
}
