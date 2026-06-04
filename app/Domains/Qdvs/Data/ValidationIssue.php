<?php

namespace App\Domains\Qdvs\Data;

use Spatie\LaravelData\Data;

class ValidationIssue extends Data
{
    public function __construct(
        public string $pseudonym,
        public string $feld,
        public string $meldung,
        // WHY(DAS_REGELN): 'fehler' blockt Export, 'warnung' erlaubt ihn
        public string $schwere = 'fehler',
    ) {}
}
