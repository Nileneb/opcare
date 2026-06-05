<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Medication\Models\Prescription;

class MedicationStatementMapper
{
    public static function id(Prescription $p): string
    {
        return 'medicationstatement-'.$p->id;
    }

    /** @return array<string, mixed> */
    public function map(Prescription $p, string $patientReference): array
    {
        $product = $p->medProduct;

        $statement = [
            'resourceType' => 'MedicationStatement',
            'id' => self::id($p),
            'status' => $p->ist_aktiv ? 'active' : 'stopped',
            'medicationCodeableConcept' => $this->medication($product),
            'subject' => ['reference' => $patientReference],
            'effectivePeriod' => array_filter([
                'start' => $p->gueltig_von?->toDateString(),
                'end' => $p->gueltig_bis?->toDateString(),
            ]),
        ];

        $dosage = $this->dosage($p);
        if ($dosage !== null) {
            $statement['dosage'] = [$dosage];
        }

        return $statement;
    }

    /** @return array<string, mixed> */
    private function medication(?object $product): array
    {
        if (! $product) {
            return ['text' => 'Unbekanntes Präparat'];
        }

        $coding = [];
        if ($product->pzn) {
            $coding[] = ['system' => 'http://fhir.de/CodeSystem/ifa/pzn', 'code' => (string) $product->pzn];
        }
        if ($product->atc_code) {
            $coding[] = ['system' => 'http://fhir.de/CodeSystem/bfarm/atc', 'code' => (string) $product->atc_code];
        }

        $text = (string) $product->name;
        if ($product->staerke && ! str_contains($text, (string) $product->staerke)) {
            $text .= ' '.$product->staerke;
        }

        $concept = ['text' => trim($text)];
        if ($coding !== []) {
            $concept['coding'] = $coding;
        }

        return $concept;
    }

    /** @return array<string, mixed>|null */
    private function dosage(Prescription $p): ?array
    {
        if ($p->bei_bedarf) {
            return ['text' => 'Bei Bedarf', 'asNeededBoolean' => true];
        }

        $parts = $p->schedules->flatMap(function ($s) {
            return collect(is_array($s->dosis) ? $s->dosis : [])
                ->filter(fn ($menge) => $menge)
                ->map(fn ($menge, $zeit) => "{$zeit} {$menge}");
        })->all();

        return $parts === [] ? null : ['text' => implode(', ', $parts)];
    }
}
