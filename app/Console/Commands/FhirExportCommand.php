<?php

namespace App\Console\Commands;

use App\Domains\Fhir\FhirDocumentExporter;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentDiagnosis;
use Illuminate\Console\Command;

class FhirExportCommand extends Command
{
    protected $signature = 'fhir:export {resident? : Bewohner-ID (Default: erster Bewohner)} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert einen Bewohner als FHIR-R4-Document-Bundle (Pflegebericht)';

    public function handle(FhirDocumentExporter $exporter): int
    {
        // Default: ein Bewohner mit Diagnosen (vollständiges Dokument fürs CI-Gate), sonst der erste
        $id = $this->argument('resident')
            ?? ResidentDiagnosis::withoutGlobalScopes()->orderBy('resident_id')->value('resident_id')
            ?? Resident::withoutGlobalScopes()->orderBy('id')->value('id');
        $resident = $id ? Resident::withoutGlobalScopes()->find($id) : null;

        if (! $resident) {
            $this->error('Kein Bewohner gefunden.');

            return self::FAILURE;
        }

        // WHY: CLI-Kontext hat keinen Mandanten-Scope — für die tenant-gescopten Relationen setzen
        app(CurrentTenant::class)->set(Tenant::findOrFail($resident->tenant_id));

        $json = $exporter->toJson($resident);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("FHIR-Bundle geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
