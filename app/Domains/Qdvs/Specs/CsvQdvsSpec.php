<?php

namespace App\Domains\Qdvs\Specs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Qdvs\Contracts\QdvsSpec;
use App\Domains\Quality\Enums\QualityIndicator;

class CsvQdvsSpec implements QdvsSpec
{
    public function key(): string
    {
        return 'csv-v1';
    }

    public function label(): string
    {
        return 'CSV (OPCare v1)';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function filename(string $stichtag): string
    {
        return "qdvs-export-{$stichtag}.csv";
    }

    public function render(array $packages, Tenant $tenant, string $stichtag): string
    {
        $indikatoren = array_map(fn ($i) => $i->value, QualityIndicator::cases());
        $header = array_merge(
            ['einrichtung_ik', 'stichtag', 'pseudonym', 'geburtsjahr', 'geschlecht', 'pflegegrad', 'aufnahme_am', 'icd_codes'],
            $indikatoren,
        );

        $rows = [$this->line($header)];
        foreach ($packages as $p) {
            $row = [
                $tenant->ik_nummer, $stichtag, $p->pseudonym, $p->geburtsjahr, $p->geschlecht,
                $p->pflegegrad, $p->aufnahme_am, implode('|', $p->icd_codes),
            ];
            foreach ($indikatoren as $key) {
                $val = $p->indikatoren[$key] ?? false;
                $row[] = is_bool($val) ? ($val ? '1' : '0') : (string) $val;
            }
            $rows[] = $this->line($row);
        }

        return implode("\n", $rows)."\n";
    }

    private function line(array $fields): string
    {
        return implode(';', array_map(fn ($f) => '"'.str_replace('"', '""', (string) $f).'"', $fields));
    }
}
