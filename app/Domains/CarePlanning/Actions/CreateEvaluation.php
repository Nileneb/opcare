<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Data\EvaluationData;
use App\Domains\CarePlanning\Models\Evaluation;

class CreateEvaluation
{
    public function handle(EvaluationData $data): Evaluation
    {
        return Evaluation::create($data->toArray());
    }
}
