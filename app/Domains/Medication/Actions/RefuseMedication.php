<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\MedicationAdministration;

class RefuseMedication
{
    /** $status: Abgelehnt (Bewohner verweigert) oder Ausgelassen (z. B. nüchtern/abwesend). */
    public function handle(
        MedicationAdministration $a,
        int $userId,
        string $notiz,
        AdministrationStatus $status = AdministrationStatus::Abgelehnt,
    ): MedicationAdministration {
        $a->update([
            'status' => $status,
            'ist_zeitpunkt' => now(),
            'quittiert_von' => $userId,
            'notiz' => $notiz,
        ]);

        return $a;
    }
}
