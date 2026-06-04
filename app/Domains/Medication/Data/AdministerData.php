<?php

namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class AdministerData extends Data
{
    public function __construct(
        public int $quittiert_von,
        public ?int $med_product_id = null,
        public ?float $dosis = null,
        public ?string $notiz = null,
    ) {}
}
