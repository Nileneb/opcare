<?php

namespace App\Domains\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'leserecht'] as $role) {
            Role::findOrCreate($role);
        }
    }
}
