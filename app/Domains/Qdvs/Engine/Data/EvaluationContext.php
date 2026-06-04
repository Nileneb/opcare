<?php

namespace App\Domains\Qdvs\Engine\Data;

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use Carbon\CarbonImmutable;

class EvaluationContext
{
    public function __construct(
        public QdvsResidentPackage $package,
        public FieldMap $map,
        // WHY(DAS_REGELN): current-date() in den Asserts = Verarbeitungstag, nicht der Stichtag
        public CarbonImmutable $today,
    ) {}

    public function raw(string $dasField): mixed
    {
        return $this->map->value($dasField, $this->package);
    }
}
