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
     * @return array<string, mixed>
     */
    public function map(Resident $r, Collection $reports, string $patientReference, string $date): array
    {
        return [
            'resourceType' => 'Composition',
            'id' => self::id($r),
            'status' => 'final',
            'type' => [
                'coding' => [[
                    'system' => 'http://loinc.org',
                    'code' => '34746-8',
                    'display' => 'Nurse Note',
                ]],
                'text' => 'Pflegebericht',
            ],
            'subject' => ['reference' => $patientReference],
            'date' => $date,
            'author' => [['display' => 'OPCare']],
            'title' => 'Pflegebericht',
            'section' => [[
                'title' => 'Berichteinträge',
                'text' => [
                    'status' => 'generated',
                    'div' => $this->narrative($reports),
                ],
            ]],
        ];
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
