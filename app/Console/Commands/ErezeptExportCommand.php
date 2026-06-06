<?php

namespace App\Console\Commands;

use App\Domains\Fhir\Erezept\ErezeptBundleMapper;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Medication\Models\Prescription;
use Illuminate\Console\Command;

/**
 * Exportiert eine Verordnung als KBV-E-Rezept-Bundle (Muster 16, KBV_PR_ERP_Bundle, Datenebene).
 * Konformität wird im CI gegen den offiziellen gematik Referenzvalidator (Modul erp) geprüft.
 */
class ErezeptExportCommand extends Command
{
    protected $signature = 'erezept:export {prescription? : Verordnungs-ID} {--output= : Datei statt stdout}';

    protected $description = 'Exportiert eine Verordnung als KBV-E-Rezept-Bundle (Muster 16)';

    public function handle(ErezeptBundleMapper $mapper): int
    {
        $prescription = Prescription::withoutGlobalScopes()
            ->when($this->argument('prescription'), fn ($q, $id) => $q->whereKey($id))
            ->whereNotNull('med_product_id')
            ->with(['resident.physicians', 'resident.insurances.healthInsurance', 'medProduct', 'schedules'])
            ->orderBy('id')->first();

        if (! $prescription) {
            $this->error('Keine geeignete Verordnung gefunden.');

            return self::FAILURE;
        }
        app(CurrentTenant::class)->set(Tenant::findOrFail($prescription->tenant_id));

        $arzt = $prescription->resident->physicians->first(fn (Physician $p) => $p->lanr && $p->bsnr);
        $ins = $prescription->resident->insurances->first(fn ($i) => $i->versichertennr !== null);

        if (! $arzt) {
            $this->error('Bewohner hat keinen Arzt mit LANR + BSNR — für das E-Rezept erforderlich.');

            return self::FAILURE;
        }
        if (! $ins) {
            $this->error('Bewohner hat keine Versichertennummer (KVNR).');

            return self::FAILURE;
        }

        $json = json_encode($mapper->build($prescription, $arzt, $ins), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->info("E-Rezept-Bundle geschrieben: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
