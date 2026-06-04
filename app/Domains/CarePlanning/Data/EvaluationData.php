<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class EvaluationData extends Data
{
    public function __construct(
        public string $evaluable_type,
        public int $evaluable_id,
        public int $created_by,
        public string $datum,
        public string $zielerreichung,
        public ?string $anlass = null,
    ) {}
}
