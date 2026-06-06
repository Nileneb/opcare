<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Evp\EvpPflegegradMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Console\Command;

/**
 * Exportiert den Pflegegrad eines Bewohners als GKV-SV-EVP `GKVSV_PR_EVP_Pflegegrad`.
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul evp) geprüft.
 */
class EvpExportCommand extends Command
{
    protected $signature = 'evp:export {resident? : Bewohner-ID (Default: erster Bewohner mit Pflegegrad)} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert den Pflegegrad eines Bewohners als GKV-SV-EVP-Ressource';

    public function handle(EvpPflegegradMapper $mapper): int
    {
        $id = $this->argument('resident')
            ?? Resident::withoutGlobalScopes()->whereNotNull('pflegegrad')->orderBy('id')->value('id')
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
            $this->info("EVP-Ressource geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
