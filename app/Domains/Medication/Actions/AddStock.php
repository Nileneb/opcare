<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\StockStatus;
use App\Domains\Medication\Enums\StockTransactionType;
use App\Domains\Medication\Models\MedInventory;
use App\Domains\Medication\Models\MedStock;
use Illuminate\Support\Facades\DB;

class AddStock
{
    public function handle(StockData $data): MedStock
    {
        return DB::transaction(function () use ($data) {
            $inventory = MedInventory::firstOrCreate([
                'resident_id' => $data->resident_id,
                'med_product_id' => $data->med_product_id,
            ]);

            $stock = $inventory->stocks()->create([
                'menge_initial' => $data->menge,
                'menge_aktuell' => $data->menge,
                'einheit' => $data->einheit,
                'charge' => $data->charge,
                'eingang_am' => now()->toDateString(),
                'verfall_am' => $data->verfall_am,
                'status' => StockStatus::Vorraetig,
            ]);

            $stock->transactions()->create([
                'typ' => StockTransactionType::Zugang,
                'menge' => $data->menge,
                'gebucht_am' => now(),
                'gebucht_von' => auth()->id(),
            ]);

            return $stock;
        });
    }
}
