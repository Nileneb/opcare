<?php

namespace App\Console\Commands;

use App\Domains\Masterdata\Actions\ImportIcdCatalog;
use Illuminate\Console\Command;

class IcdImportCommand extends Command
{
    protected $signature = 'icd:import {file? : Pfad zur ICD-Datei (BfArM syst_kodes.txt oder code;bezeichnung). Standard: gebündelte ICD-10-GM 2017}';

    protected $description = 'Importiert den ICD-10-GM-Katalog in die icd_codes-Tabelle (idempotent)';

    public function handle(ImportIcdCatalog $import): int
    {
        $path = $this->argument('file') ?? database_path(ImportIcdCatalog::BUNDLED);

        $this->info("Importiere ICD-Katalog aus: {$path}");
        $count = $import->handle($path);
        $this->info("{$count} ICD-Codes importiert.");

        return self::SUCCESS;
    }
}
