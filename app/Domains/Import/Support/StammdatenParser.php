<?php

namespace App\Domains\Import\Support;

final class StammdatenParser
{
    /**
     * Parses CSV content (with optional UTF-8 BOM, auto-detected delimiter).
     *
     * @return array{header: string[], zeilen: array<int, array<string, string>>, mapping: array<string, string|null>}
     */
    public static function parseCsv(string $inhalt): array
    {
        $inhalt = ltrim($inhalt, "\xEF\xBB\xBF");

        $zeilen = preg_split('/\r\n|\r|\n/', $inhalt);
        $zeilen = array_filter($zeilen, fn (string $z): bool => $z !== '');
        $zeilen = array_values($zeilen);

        if ($zeilen === []) {
            return ['header' => [], 'zeilen' => [], 'mapping' => SpaltenAlias::erkenne([])];
        }

        $ersteZeile = $zeilen[0];
        $candidates = [';', ',', "\t", '|'];
        $counts = array_map(fn (string $d): int => substr_count($ersteZeile, $d), $candidates);
        $max = max($counts);
        $delimiter = $max > 0 ? $candidates[array_search($max, $counts)] : ';';

        $header = array_map('trim', str_getcsv($ersteZeile, $delimiter));

        $daten = [];
        foreach (array_slice($zeilen, 1) as $zeile) {
            $werte = str_getcsv($zeile, $delimiter);
            $row = [];
            foreach ($header as $i => $spalte) {
                $row[$spalte] = $werte[$i] ?? '';
            }
            $daten[] = $row;
        }

        return [
            'header' => $header,
            'zeilen' => $daten,
            'mapping' => SpaltenAlias::erkenne($header),
        ];
    }
}
