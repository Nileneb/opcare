<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;

class ObservationMapper
{
    public static function id(VitalReading $v): string
    {
        return 'observation-'.$v->id;
    }

    /** @return array<string, mixed> */
    public function map(VitalReading $v, string $patientReference): array
    {
        [$loinc, $display, $ucum] = $this->coding($v->typ);

        $observation = [
            'resourceType' => 'Observation',
            'id' => self::id($v),
            'status' => 'final',
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                    'code' => 'vital-signs',
                ]],
            ]],
            'code' => [
                'coding' => [['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $display]],
                'text' => $display,
            ],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $v->gemessen_am?->toIso8601String(),
        ];

        if ($v->typ === VitalType::Blutdruck) {
            $observation['component'] = [
                $this->component('8480-6', 'Systolic blood pressure', (float) $v->wert, 'mm[Hg]', 'mmHg'),
                $this->component('8462-4', 'Diastolic blood pressure', (float) $v->wert2, 'mm[Hg]', 'mmHg'),
            ];

            return $observation;
        }

        $observation['valueQuantity'] = $this->quantity((float) $v->wert, $v->einheit ?: '', $ucum);

        return $observation;
    }

    /** @return array{0: string, 1: string, 2: ?string} [LOINC, Anzeige, UCUM-Code] */
    private function coding(VitalType $typ): array
    {
        return match ($typ) {
            VitalType::Gewicht => ['29463-7', 'Körpergewicht', 'kg'],
            VitalType::Blutdruck => ['85354-9', 'Blutdruck', null],
            VitalType::Puls => ['8867-4', 'Herzfrequenz', '/min'],
            VitalType::Temperatur => ['8310-5', 'Körpertemperatur', 'Cel'],
            VitalType::Blutzucker => ['15074-8', 'Blutzucker', 'mg/dL'],
            VitalType::SpO2 => ['59408-5', 'Sauerstoffsättigung', '%'],
            VitalType::Atemfrequenz => ['9279-1', 'Atemfrequenz', '/min'],
            VitalType::Schmerz => ['72514-3', 'Schmerzstärke (NRS 0–10)', null],
        };
    }

    /** @return array<string, mixed> */
    private function quantity(float $value, string $unit, ?string $ucum): array
    {
        $q = ['value' => $value, 'unit' => $unit !== '' ? $unit : ($ucum ?? 'Punkte')];
        if ($ucum !== null) {
            $q['system'] = 'http://unitsofmeasure.org';
            $q['code'] = $ucum;
        }

        return $q;
    }

    /** @return array<string, mixed> */
    private function component(string $loinc, string $display, float $value, string $ucum, string $unit): array
    {
        return [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $loinc, 'display' => $display]]],
            'valueQuantity' => $this->quantity($value, $unit, $ucum),
        ];
    }
}
