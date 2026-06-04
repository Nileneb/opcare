<?php

namespace Database\Seeders;

use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
