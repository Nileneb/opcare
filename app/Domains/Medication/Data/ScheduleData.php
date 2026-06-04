<?php

namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class ScheduleData extends Data
{
    public function __construct(
        public string $frequenz,
        public array $dosis,
        public int $intervall = 1,
        public ?array $wochentage = null,
        public ?float $max_anzahl_taeglich = null,
        public ?float $max_einzeldosis = null,
    ) {}
}
