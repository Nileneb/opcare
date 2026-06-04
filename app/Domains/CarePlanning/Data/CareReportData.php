<?php

namespace App\Domains\CarePlanning\Data;

use Spatie\LaravelData\Data;

class CareReportData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public string $datum,
        public string $schicht,
        public string $text,
    ) {}
}
