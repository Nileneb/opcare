<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class CareMeasureData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $themenfeld,
        public string $beschreibung,
        public ?string $ziel = null,
        public ?string $verantwortlich = null,
    ) {}
}
