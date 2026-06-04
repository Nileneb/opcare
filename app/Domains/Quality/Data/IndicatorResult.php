<?php

namespace App\Domains\Quality\Data;

use Spatie\LaravelData\Data;

class IndicatorResult extends Data
{
    public function __construct(
        public string $indicator,
        public string $art,
        public int $betroffene,
        public int $kohorte,
    ) {}

    public function quote(): float
    {
        return $this->kohorte > 0 ? round($this->betroffene / $this->kohorte * 100, 1) : 0.0;
    }
}
