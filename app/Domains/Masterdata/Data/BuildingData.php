<?php

namespace App\Domains\Masterdata\Data;

use Spatie\LaravelData\Data;

class BuildingData extends Data
{
    public function __construct(
        public string $name,
    ) {}
}
