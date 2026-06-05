<?php

namespace Database\Seeders;

use App\Domains\CarePlanning\Database\Seeders\MeasureCatalogSeeder;
use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Database\Seeders\SuperAdminRoleSeeder;
use App\Domains\Masterdata\Database\Seeders\IcdCodeSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            SuperAdminRoleSeeder::class,
            IcdCodeSeeder::class,
            MeasureCatalogSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
