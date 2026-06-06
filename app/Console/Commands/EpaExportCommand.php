<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Epa\EpaMedicationMapper;
use App\Domains\Medication\Models\MedProduct;
use Illuminate\Console\Command;

/**
 * Exportiert ein Medikament als gematik-ePA `epa-medication` (Medikationsliste der elektronischen Patientenakte).
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul epa3-medication) geprüft.
 */
class EpaExportCommand extends Command
{
    protected $signature = 'epa:export {medproduct? : MedProduct-ID (Default: erstes Produkt mit PZN)} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert ein Medikament als gematik-ePA epa-medication';

    public function handle(EpaMedicationMapper $mapper): int
    {
        $id = $this->argument('medproduct')
            ?? MedProduct::withoutGlobalScopes()->whereNotNull('pzn')->where('pzn', '!=', '')->orderBy('id')->value('id')
            ?? MedProduct::withoutGlobalScopes()->orderBy('id')->value('id');
        $product = $id ? MedProduct::withoutGlobalScopes()->find($id) : null;

        if (! $product) {
            $this->error('Kein MedProduct gefunden.');

            return self::FAILURE;
        }

        $json = json_encode($mapper->map($product), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("ePA-Ressource geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
