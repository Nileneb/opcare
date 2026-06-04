<?php

namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class StockData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $med_product_id,
        public float $menge,
        public string $einheit,
        public ?string $charge = null,
        public ?string $verfall_am = null,
    ) {}
}
