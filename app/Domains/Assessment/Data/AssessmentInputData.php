<?php

namespace App\Domains\Assessment\Data;

use Spatie\LaravelData\Data;

class AssessmentInputData extends Data
{
    /**
     * @param  array<int, int>  $answers  instrument_item_id => assessment_option_id
     */
    public function __construct(
        public int $resident_id,
        public int $instrument_id,
        public int $created_by,
        public array $answers,
        public ?string $durchgefuehrt_am = null,
        public ?string $notiz = null,
    ) {}
}
