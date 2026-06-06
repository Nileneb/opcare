<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Isip\IsipPatientMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Console\Command;

/**
 * Exportiert einen Bewohner als gematik-ISiP-`ISiPPflegeempfaenger`-Ressource (Pflege-Interop-Norm).
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul isip1) geprüft.
 */
class IsipExportCommand extends Command
{
    protected $signature = 'isip:export {resident? : Bewohner-ID (Default: erster Bewohner)} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert einen Bewohner als gematik-ISiP ISiPPflegeempfaenger (Pflege-Interop)';

    public function handle(IsipPatientMapper $mapper): int
    {
        $id = $this->argument('resident')
            ?? Resident::withoutGlobalScopes()->orderBy('id')->value('id');
        $resident = $id ? Resident::withoutGlobalScopes()->find($id) : null;

        if (! $resident) {
            $this->error('Kein Bewohner gefunden.');

            return self::FAILURE;
        }

        app(CurrentTenant::class)->set(Tenant::findOrFail($resident->tenant_id));

        $json = json_encode($mapper->map($resident), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("ISiP-Ressource geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
