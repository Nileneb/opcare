<?php

namespace App\Domains\Scheduling\Data;

use Spatie\LaravelData\Data;

class ShiftAssignmentData extends Data
{
    public function __construct(
        public int $user_id,
        public int $shift_id,
        public string $dienst_am,
        public ?string $notiz = null,
    ) {}
}
