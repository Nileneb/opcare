<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Collection;

class CompositionMapper
{
    public static function id(Resident $r): string
    {
        return 'composition-'.$r->id;
    }

    /**
     * @param  Collection<int, CareReport>  $reports
     * @param  array<int, string>  $conditionRefs
     * @param  array<int, string>  $observationRefs
     * @return array<string, mixed>
     */
    public function map(
        Resident $r,
        string $patientReference,
        string $date,
        Collection $reports,
        array $conditionRefs = [],
        ?string $carePlanRef = null,
        array $observationRefs = [],
    ): array {
        $sections = [];
        if ($conditionRefs !== []) {
            $sections[] = ['title' => 'Diagnosen', 'entry' => $this->entries($conditionRefs)];
        }
        if ($carePlanRef !== null) {
            $sections[] = ['title' => 'Pflegeplan', 'entry' => $this->entries([$carePlanRef])];
        }
        if ($observationRefs !== []) {
            $sections[] = ['title' => 'Beobachtungen / Vitalwerte', 'entry' => $this->entries($observationRefs)];
        }
        $sections[] = ['title' => 'Verlauf', 'text' => ['status' => 'generated', 'div' => $this->narrative($reports)]];

        return [
            'resourceType' => 'Composition',
            'id' => self::id($r),
            'status' => 'final',
            'type' => [
                'coding' => [['system' => 'http://loinc.org', 'code' => '34746-8', 'display' => 'Nurse Note']],
                'text' => 'Pflegebericht',
            ],
            'subject' => ['reference' => $patientReference],
            'date' => $date,
            'author' => [['display' => 'OPCare']],
            'title' => 'Pflegebericht',
            'section' => $sections,
        ];
    }

    /**
     * @param  array<int, string>  $refs
     * @return array<int, array{reference: string}>
     */
    private function entries(array $refs): array
    {
        return array_map(fn (string $ref) => ['reference' => $ref], $refs);
    }

    /** @param Collection<int, CareReport> $reports */
    private function narrative(Collection $reports): string
    {
        $rows = $reports
            ->map(fn ($rep) => '<p><b>'.e($rep->datum->format('d.m.Y H:i')).' ('.e($rep->schicht->value).')</b>: '.e($rep->text).'</p>')
            ->implode('');

        if ($rows === '') {
            $rows = '<p>Keine Berichteinträge.</p>';
        }

        return '<div xmlns="http://www.w3.org/1999/xhtml">'.$rows.'</div>';
    }
}
