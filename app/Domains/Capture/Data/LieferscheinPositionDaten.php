<?php

namespace App\Domains\Capture\Data;

use Spatie\LaravelData\Data;

class LieferscheinPositionDaten extends Data
{
    public function __construct(
        public string $text,
        public ?float $menge = null,
        public ?string $einheit = null,
        public ?float $einzelpreis = null,
        public ?string $charge_nr = null,
        public ?string $mhd = null,
    ) {}
}
