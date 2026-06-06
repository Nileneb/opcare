<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Epa\EpaMedicationMapper;
use App\Domains\Fhir\Epa\EpaMedicationRequestMapper;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Prescription;
use Illuminate\Console\Command;

/**
 * Exportiert eine gematik-ePA-Ressource der Medikationsliste (elektronische Patientenakte).
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul epa3-medication) geprüft.
 */
class EpaExportCommand extends Command
{
    protected $signature = 'epa:export {resource=medication : medication|medication-request} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert eine gematik-ePA-Ressource (epa-medication / epa-medication-request)';

    public function handle(): int
    {
        $json = match ($this->argument('resource')) {
            'medication-request' => $this->medicationRequest(),
            default => $this->medication(),
        };

        if ($json === null) {
            return self::FAILURE;
        }

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("ePA-Ressource geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    private function medication(): ?string
    {
        $product = MedProduct::withoutGlobalScopes()->whereNotNull('pzn')->where('pzn', '!=', '')->orderBy('id')->first()
            ?? MedProduct::withoutGlobalScopes()->orderBy('id')->first();

        if (! $product) {
            $this->error('Kein MedProduct gefunden.');

            return null;
        }

        return $this->encode((new EpaMedicationMapper)->map($product));
    }

    private function medicationRequest(): ?string
    {
        $prescription = Prescription::withoutGlobalScopes()->whereNotNull('med_product_id')->with('medProduct')->orderBy('id')->first();
        if (! $prescription) {
            $this->error('Keine Verordnung mit Medikament gefunden.');

            return null;
        }

        $kvnr = $prescription->resident->insurances()->where('ist_primaer', true)->value('versichertennr')
            ?? $prescription->resident->insurances()->value('versichertennr');
        if (! $kvnr) {
            $this->error('Bewohner hat keine Versichertennummer (KVNR) — für epa-medication-request erforderlich.');

            return null;
        }

        return $this->encode((new EpaMedicationRequestMapper)->map($prescription, $kvnr));
    }

    /** @param array<string, mixed> $resource */
    private function encode(array $resource): string
    {
        return json_encode($resource, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
