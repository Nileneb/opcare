<?php

namespace App\Domains\Speech\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SisVorschlagData extends Data
{
    public function __construct(
        /** @var DataCollection<int, SisVorschlagFieldData> */
        public DataCollection $felder,
    ) {}
}
