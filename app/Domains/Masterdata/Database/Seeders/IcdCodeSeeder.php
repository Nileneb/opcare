<?php

namespace App\Domains\Masterdata\Database\Seeders;

use App\Domains\Masterdata\Actions\ImportIcdCatalog;
use Illuminate\Database\Seeder;

class IcdCodeSeeder extends Seeder
{
    public function run(): void
    {
        // WHY: ICD-Katalog ist global (kein tenant_id) → einmalig, vor den Mandanten-Demodaten
        app(ImportIcdCatalog::class)->handle(database_path(ImportIcdCatalog::BUNDLED));
    }
}
