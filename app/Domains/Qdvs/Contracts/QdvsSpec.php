<?php

namespace App\Domains\Qdvs\Contracts;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Qdvs\Data\QdvsResidentPackage;

interface QdvsSpec
{
    public function key(): string;

    public function label(): string;

    /** @param array<int, QdvsResidentPackage> $packages */
    public function render(array $packages, Tenant $tenant, string $stichtag): string;

    public function filename(string $stichtag): string;

    public function mimeType(): string;
}
