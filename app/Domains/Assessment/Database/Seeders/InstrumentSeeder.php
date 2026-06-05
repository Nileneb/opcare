<?php

namespace App\Domains\Assessment\Database\Seeders;

use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Support\InstrumentReferenceData;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    // Legt die Start-Instrumente für den AKTUELLEN Mandanten an. Idempotent über den Namen.
    public function run(): void
    {
        foreach (InstrumentReferenceData::instruments() as $def) {
            $instrument = Instrument::firstOrCreate(
                ['name' => $def['name']],
                [
                    'loinc' => $def['loinc'] ?? null,
                    'risk_type' => $def['risk_type'],
                    'direction' => $def['direction'],
                    'risk_bands' => $def['risk_bands'],
                    'intervall_tage' => $def['intervall_tage'],
                ],
            );

            if ($instrument->items()->exists()) {
                continue; // bereits befüllt
            }

            foreach ($def['items'] as $i => $itemDef) {
                $item = $instrument->items()->create(['label' => $itemDef['label'], 'loinc' => $itemDef['loinc'] ?? null, 'reihenfolge' => $i]);
                foreach ($itemDef['options'] as $o => $optDef) {
                    $item->options()->create([
                        'label' => $optDef['label'], 'punkte' => $optDef['punkte'], 'reihenfolge' => $o,
                    ]);
                }
            }
        }
    }
}
