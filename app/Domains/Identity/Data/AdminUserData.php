<?php
namespace App\Domains\Identity\Data;

use Spatie\LaravelData\Data;

class AdminUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
        public string $role = 'pflegehilfskraft',
    ) {}
}
