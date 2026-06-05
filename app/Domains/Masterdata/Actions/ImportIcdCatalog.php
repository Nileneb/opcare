<?php

namespace App\Domains\Masterdata\Actions;

use App\Domains\Masterdata\Models\IcdCode;
use Illuminate\Support\Carbon;
use RuntimeException;

class ImportIcdCatalog
{
    public const BUNDLED = 'data/icd/icd10gm_2017_kodes.csv';

    /**
     * Importiert den ICD-10-GM-Katalog idempotent (upsert je Code).
     *
     * Erkennt zwei Formate automatisch:
     *  - schlank:  code;bezeichnung  (gebündelte Datei)
     *  - amtlich:  BfArM syst_kodes.txt (≥9 Spalten, Code=Feld 7, Bezeichnung=Feld 9)
     *
     * @return int Anzahl importierter Codes
     */
    public function handle(string $path): int
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("ICD-Katalogdatei nicht lesbar: {$path}");
        }

        try {
            $now = Carbon::now();
            $batch = [];
            $total = 0;
            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                [$code, $bezeichnung] = $this->columns($row);
                if ($code === '' || $bezeichnung === '') {
                    continue;
                }

                $batch[$code] = ['code' => $code, 'bezeichnung' => $bezeichnung, 'created_at' => $now, 'updated_at' => $now];
                if (count($batch) >= 1000) {
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

    /**
     * @param  array<int, string|null>  $row
     * @return array{0: string, 1: string}
     */
    private function columns(array $row): array
    {
        // amtliches syst_kodes.txt: Feld 7 = Schlüsselnummer, Feld 9 = Klassentitel
        if (count($row) >= 9) {
            return [trim((string) ($row[6] ?? '')), trim((string) ($row[8] ?? ''))];
        }

        return [trim((string) ($row[0] ?? '')), trim((string) ($row[1] ?? ''))];
    }

    /** @param array<string, array<string, mixed>> $batch */
    private function flush(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }
        IcdCode::upsert(array_values($batch), ['code'], ['bezeichnung', 'updated_at']);

        return count($batch);
    }
}
