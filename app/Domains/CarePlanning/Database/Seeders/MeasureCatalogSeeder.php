<?php

namespace App\Domains\CarePlanning\Database\Seeders;

use App\Domains\CarePlanning\Actions\ImportMeasureCatalog;
use Illuminate\Database\Seeder;

class MeasureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // WHY: Maßnahmen-Katalog ist global (kein tenant_id) → einmalig
        app(ImportMeasureCatalog::class)->handle(database_path(ImportMeasureCatalog::BUNDLED));
    }
}
