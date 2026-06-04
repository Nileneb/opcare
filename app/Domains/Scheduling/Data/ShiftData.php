<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\ShiftKind;
use Spatie\LaravelData\Data;

class ShiftData extends Data
{
    public function __construct(
        public string $name,
        public ShiftKind $kind,
        public string $beginn,
        public string $ende,
        public ?array $timeslots = null,
        public bool $aktiv = true,
    ) {}
}
