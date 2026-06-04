<?php
namespace App\Domains\Identity\Data;

use Spatie\LaravelData\Data;

class TenantData extends Data
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $traeger = null,
        public ?string $ik_nummer = null,
        public bool $aktiv = true,
    ) {}
}
