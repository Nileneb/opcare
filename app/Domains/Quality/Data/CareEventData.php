<?php

namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class CareEventData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $indicator,
        public string $datum,
        public ?string $severity = null,
        public ?array $details = null,
        public ?string $behoben_am = null,
        public ?int $reported_by = null,
    ) {}
}
