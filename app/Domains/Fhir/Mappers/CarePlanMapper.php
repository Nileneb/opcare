<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Collection;

class CarePlanMapper
{
    public static function id(Resident $r): string
    {
        return 'careplan-'.$r->id;
    }

    /**
     * @param  Collection<int, CareMeasure>  $measures
     * @return array<string, mixed>
     */
    public function map(Resident $r, Collection $measures, string $patientReference): array
    {
        return [
            'resourceType' => 'CarePlan',
            'id' => self::id($r),
            'status' => 'active',
            'intent' => 'plan',
            'title' => 'Pflegeplan (SIS)',
            'subject' => ['reference' => $patientReference],
            'text' => ['status' => 'generated', 'div' => $this->narrative($measures)],
            'activity' => $measures->map(fn ($m) => [
                'detail' => [
                    'status' => 'in-progress',
                    'description' => trim($m->themenfeld->value.': '.$m->beschreibung.($m->ziel ? ' (Ziel: '.$m->ziel.')' : '')),
                ],
            ])->values()->all(),
        ];
    }

    /** @param Collection<int, CareMeasure> $measures */
    private function narrative(Collection $measures): string
    {
        $rows = $measures
            ->map(fn ($m) => '<p><b>'.e($m->themenfeld->value).'</b>: '.e($m->beschreibung).'</p>')
            ->implode('');

        if ($rows === '') {
            $rows = '<p>Keine Maßnahmen geplant.</p>';
        }

        return '<div xmlns="http://www.w3.org/1999/xhtml">'.$rows.'</div>';
    }
}
