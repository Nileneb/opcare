<?php

namespace Database\Seeders;

use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Database\Seeders\SuperAdminRoleSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            SuperAdminRoleSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
