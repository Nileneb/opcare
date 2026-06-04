<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use Spatie\Permission\Models\Role;

it('seedet Pflege-Rollen', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::pluck('name')->all())
        ->toContain('admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht');
});
