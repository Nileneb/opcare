<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt eine GBU fort: setzt letzte_ueberpruefung_am als Max und Status auf Freigegeben.
 * WHY(§ 3 Abs. 1 ArbSchG): Ein nachgetragenes älteres Datum darf die Fortschreibungs-Frist nicht zurücksetzen.
 */
class GbuFortschreiben
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Gefaehrdungsbeurteilung  $gbu  Tenant-geprüfte GBU
     * @param  string  $datum  Datum im Format 'Y-m-d' (darf nicht in der Zukunft liegen — UI-Pflicht)
     */
    public function handle(Gefaehrdungsbeurteilung $gbu, string $datum): void
    {
        // WHY: Tenant-Guard — stellt sicher dass kein Cross-Tenant-Zugriff über diesen Service möglich ist
        abort_unless($gbu->tenant_id === $this->currentTenant->id(), 403);

        DB::transaction(function () use ($gbu, $datum): void {
            $datumCarbon = Carbon::parse($datum);

            // WHY: Max-Semantik — nachgetragenes älteres Datum darf die Frist nicht zurücksetzen
            $neuesDatum = ($gbu->letzte_ueberpruefung_am === null || $datumCarbon->gt($gbu->letzte_ueberpruefung_am))
                ? $datum
                : $gbu->letzte_ueberpruefung_am->toDateString();

            $gbu->update([
                'letzte_ueberpruefung_am' => $neuesDatum,
                'status' => GbuStatus::Freigegeben,
            ]);
        });
    }
}
