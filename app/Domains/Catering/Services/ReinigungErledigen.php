<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\Reinigungsaufgabe;
use App\Domains\Catering\Models\Reinigungsnachweis;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legt einen Erledigungs-Nachweis an und aktualisiert letzte_erledigung_am als Max.
 * WHY(VO 852/2004 Anhang II): Ein nachgetragener älterer Nachweis darf die Frist nicht zurücksetzen.
 */
class ReinigungErledigen
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  Reinigungsaufgabe  $aufgabe  Tenant-geprüfte Aufgabe
     * @param  string  $erledigtAm  Datum im Format 'Y-m-d' (darf nicht in der Zukunft liegen — UI-Pflicht)
     * @param  int|null  $userId  Erledigender Benutzer (null = anonym)
     * @param  string|null  $bemerkung  Optionale Bemerkung
     */
    public function handle(
        Reinigungsaufgabe $aufgabe,
        string $erledigtAm,
        ?int $userId = null,
        ?string $bemerkung = null,
    ): Reinigungsnachweis {
        return DB::transaction(function () use ($aufgabe, $erledigtAm, $userId, $bemerkung): Reinigungsnachweis {
            $nachweis = Reinigungsnachweis::create([
                'tenant_id' => $this->currentTenant->id(),
                'reinigungsaufgabe_id' => $aufgabe->id,
                'erledigt_am' => $erledigtAm,
                'erledigt_von' => $userId,
                'bemerkung' => $bemerkung,
            ]);

            $erledigtAmDate = Carbon::parse($erledigtAm);

            // WHY: Max-Semantik — nachgetragene ältere Nachweise dürfen die Frist nicht zurücksetzen
            if ($aufgabe->letzte_erledigung_am === null || $erledigtAmDate->gt($aufgabe->letzte_erledigung_am)) {
                $aufgabe->update(['letzte_erledigung_am' => $erledigtAm]);
            }

            return $nachweis;
        });
    }
}
