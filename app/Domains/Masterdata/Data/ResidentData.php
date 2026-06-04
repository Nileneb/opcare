<?php

namespace App\Domains\Masterdata\Data;

use Spatie\LaravelData\Data;

class ResidentData extends Data
{
    public function __construct(
        public string $name,
        public string $geburtsdatum,
        public string $geschlecht,
        public string $aufnahme_am,
        public ?int $pflegegrad = null,
        public string $status = 'aktiv',
        public ?int $room_id = null,
        public ?string $entlassung_am = null,
    ) {}
}
