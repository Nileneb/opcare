<?php

namespace App\Domains\Medication\Data;

use Spatie\LaravelData\Data;

class PrescriptionData extends Data
{
    public function __construct(
        public int $resident_id,
        public int $created_by,
        public ?int $med_product_id = null,
        public ?string $bhp_text = null,
        public ?int $physician_id = null,
        public ?int $situation_id = null,
        public bool $bei_bedarf = false,
        public ?string $gueltig_von = null,
        public ?string $gueltig_bis = null,
        public ?string $hinweis = null,
    ) {}
}
