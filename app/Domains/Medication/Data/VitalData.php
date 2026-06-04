<?php

namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class VitalData extends Data
{
    public function __construct(
        public int $resident_id,
        public string $typ,
        public float $wert,
        public int $gemessen_von,
        public ?float $wert2 = null,
        public ?string $notiz = null,
        public ?int $administration_id = null,
    ) {}
}
