<?php

namespace App\Domains\Medication\Database\Seeders;

use App\Domains\Medication\Models\Situation;
use App\Domains\Medication\Models\TradeForm;
use App\Domains\Medication\Support\MedicationReferenceData;
use Illuminate\Database\Seeder;

/**
 * Legt die Medikations-Stammdaten (Darreichungsformen + Bedarf-Anlässe) für den
 * AKTUELLEN Mandanten an. Setzt voraus, dass CurrentTenant gesetzt ist (Aufruf je
 * Mandant). Idempotent über den Namen.
 */
class MedicationReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MedicationReferenceData::tradeForms() as $tf) {
            TradeForm::firstOrCreate(
                ['name' => $tf['name']],
                ['einheit' => $tf['einheit'], 'teilbar' => $tf['teilbar']],
            );
        }

        foreach (MedicationReferenceData::situations() as $name) {
            Situation::firstOrCreate(['name' => $name]);
        }
    }
}
