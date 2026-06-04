<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\RecurrenceFreq;
use Spatie\LaravelData\Data;

class RecurrenceData extends Data
{
    public function __construct(
        public RecurrenceFreq $freq,
        public ?array $byday = null,
        public int $intervall = 1,
        public ?string $until = null,
        public ?int $count = null,
    ) {}
}
