<?php

namespace App\Console\Commands;

use App\Domains\CarePlanning\Actions\ImportMeasureCatalog;
use Illuminate\Console\Command;

class MeasureCatalogImportCommand extends Command
{
    protected $signature = 'measures:import {file? : Pfad zur Maßnahmen-Datei (eine Bezeichnung je Zeile). Standard: gebündelter Katalog}';

    protected $description = 'Importiert den Pflegemaßnahmen-Katalog in measure_catalog_items (idempotent)';

    public function handle(ImportMeasureCatalog $import): int
    {
        $path = $this->argument('file') ?? database_path(ImportMeasureCatalog::BUNDLED);

        $this->info("Importiere Maßnahmen-Katalog aus: {$path}");
        $count = $import->handle($path);
        $this->info("{$count} Maßnahmen importiert.");

        return self::SUCCESS;
    }
}
