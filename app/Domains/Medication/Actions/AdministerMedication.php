<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\StockStatus;
use App\Domains\Medication\Enums\StockTransactionType;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\MedStock;
use Illuminate\Support\Facades\DB;

class AdministerMedication
{
    public function handle(MedicationAdministration $administration, AdministerData $data): MedicationAdministration
    {
        return DB::transaction(function () use ($administration, $data) {
            $dosis = $data->dosis ?? (float) $administration->dosis;

            $administration->update([
                'status' => AdministrationStatus::Gegeben,
                'ist_zeitpunkt' => now(),
                'quittiert_von' => $data->quittiert_von,
                'notiz' => $data->notiz,
                'dosis' => $dosis,
            ]);

            if ($data->med_product_id) {
                $this->bucheBestandAb($administration, $data->med_product_id, $dosis, $data->quittiert_von);
            }

            return $administration;
        });
    }

    private function bucheBestandAb(MedicationAdministration $a, int $productId, float $dosis, int $userId): void
    {
        $stock = MedStock::query()
            ->whereHas('inventory', fn ($q) => $q
                ->where('resident_id', $a->resident_id)
                ->where('med_product_id', $productId))
            ->whereIn('status', [StockStatus::Vorraetig->value, StockStatus::Angebrochen->value])
            ->where('menge_aktuell', '>', 0)
            ->orderByRaw('verfall_am IS NULL, verfall_am ASC')
            ->orderBy('eingang_am')
            ->first();

        if (! $stock) {
            return;
        }

        $stock->transactions()->create([
            'administration_id' => $a->id,
            'typ' => StockTransactionType::Entnahme,
            'menge' => -1 * $dosis,
            'gebucht_am' => now(),
            'gebucht_von' => $userId,
        ]);

        $neu = (float) $stock->menge_aktuell - $dosis;
        $stock->update([
            'menge_aktuell' => max(0, $neu),
            'geoeffnet_am' => $stock->geoeffnet_am ?? now()->toDateString(),
            'status' => $neu <= 0 ? StockStatus::Leer : StockStatus::Angebrochen,
        ]);
    }
}
