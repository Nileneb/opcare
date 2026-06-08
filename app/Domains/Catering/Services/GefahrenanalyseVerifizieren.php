<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Enums\GefahrenanalyseStatus;
use App\Domains\Catering\Models\Gefahrenanalyse;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verifiziert eine HACCP-Gefahrenanalyse (Prinzip 6): setzt letzte_verifizierung_am als Max
 * und Status auf Freigegeben.
 * WHY(VO 852/2004 Art. 5): Ein nachgetragenes älteres Datum darf die Verifizierungs-Frist nicht zurücksetzen.
 */
class GefahrenanalyseVerifizieren
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Gefahrenanalyse  $analyse  Tenant-geprüfte Analyse
     * @param  string  $datum  Datum 'Y-m-d' (darf nicht in der Zukunft liegen — UI-Pflicht)
     */
    public function handle(Gefahrenanalyse $analyse, string $datum): void
    {
        // WHY: Tenant-Guard — kein Cross-Tenant-Zugriff über diesen Service.
        abort_unless($analyse->tenant_id === $this->currentTenant->id(), 403);

        DB::transaction(function () use ($analyse, $datum): void {
            $datumCarbon = Carbon::parse($datum);

            // WHY: Max-Semantik — nachgetragenes älteres Datum darf die Frist nicht zurücksetzen.
            $neuesDatum = ($analyse->letzte_verifizierung_am === null || $datumCarbon->gt($analyse->letzte_verifizierung_am))
                ? $datum
                : $analyse->letzte_verifizierung_am->toDateString();

            $analyse->update([
                'letzte_verifizierung_am' => $neuesDatum,
                'status' => GefahrenanalyseStatus::Freigegeben,
            ]);
        });
    }
}
