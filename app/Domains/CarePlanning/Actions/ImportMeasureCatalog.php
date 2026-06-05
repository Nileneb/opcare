<?php

namespace App\Domains\CarePlanning\Actions;

use App\Domains\CarePlanning\Models\MeasureCatalogItem;
use Illuminate\Support\Carbon;
use RuntimeException;

class ImportMeasureCatalog
{
    public const BUNDLED = 'data/measures/pflege_massnahmen.csv';

    /**
     * Importiert den Pflegemaßnahmen-Katalog idempotent (eine Bezeichnung je Zeile).
     *
     * @return int Anzahl importierter Maßnahmen
     */
    public function handle(string $path): int
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Maßnahmen-Katalogdatei nicht lesbar: {$path}");
        }

        try {
            $now = Carbon::now();
            $batch = [];
            $total = 0;
            while (($line = fgets($handle)) !== false) {
                $bezeichnung = trim($line);
                if ($bezeichnung === '') {
                    continue;
                }

                $batch[mb_strtolower($bezeichnung)] = ['bezeichnung' => $bezeichnung, 'created_at' => $now, 'updated_at' => $now];
                if (count($batch) >= 500) {
                    $total += $this->flush($batch);
                    $batch = [];
                }
            }
            $total += $this->flush($batch);

            return $total;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, array<string, mixed>> $batch */
    private function flush(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }
        MeasureCatalogItem::upsert(array_values($batch), ['bezeichnung'], ['updated_at']);

        return count($batch);
    }
}
