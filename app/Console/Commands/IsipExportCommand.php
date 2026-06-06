<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Isip\IsipEncounterMapper;
use App\Domains\Fhir\Isip\IsipOrganizationMapper;
use App\Domains\Fhir\Isip\IsipPatientMapper;
use App\Domains\Fhir\Isip\IsipPractitionerMapper;
use App\Domains\Fhir\Isip\IsipRelatedPersonMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\ResidentContact;
use Illuminate\Console\Command;

/**
 * Exportiert eine gematik-ISiP-Ressource (Pflege-Interop-Norm) eines Bewohners/Mandanten.
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul isip1) geprüft.
 */
class IsipExportCommand extends Command
{
    protected $signature = 'isip:export {resource=patient : patient|encounter|organization|angehoeriger|person} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert eine gematik-ISiP-Ressource (komplettes Basismodul)';

    public function handle(): int
    {
        $resident = Resident::withoutGlobalScopes()->orderBy('id')->first();
        if (! $resident) {
            $this->error('Kein Bewohner gefunden.');

            return self::FAILURE;
        }
        app(CurrentTenant::class)->set($tenant = Tenant::findOrFail($resident->tenant_id));

        $resource = match ($this->argument('resource')) {
            'encounter' => (new IsipEncounterMapper)->map($resident),
            'organization' => (new IsipOrganizationMapper)->map($tenant),
            'angehoeriger' => (new IsipRelatedPersonMapper)->map(ResidentContact::withoutGlobalScopes()->orderBy('id')->firstOrFail()),
            'person' => (new IsipPractitionerMapper)->map(Physician::withoutGlobalScopes()->orderBy('id')->firstOrFail()),
            default => (new IsipPatientMapper)->map($resident),
        };

        $json = json_encode($resource, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("ISiP-Ressource geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
