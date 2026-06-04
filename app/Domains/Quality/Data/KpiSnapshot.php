<?php

namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class KpiSnapshot extends Data
{
    public function __construct(
        public int $bewohnerAktiv,
        public array $pflegegradVerteilung,
        public int $betten,
        public int $belegt,
    ) {}

    public function auslastung(): float
    {
        return $this->betten > 0 ? round($this->belegt / $this->betten * 100, 1) : 0.0;
    }
}
