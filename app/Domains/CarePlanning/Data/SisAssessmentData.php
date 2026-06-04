<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class SisAssessmentData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public string $erstellt_am,
        public ?string $eingangsfrage = null,
        /** @var array<int, array{themenfeld:string, freitext:?string, strukturdaten:?array}> */
        public array $themenfelder = [],
    ) {}
}
