<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;

/**
 * VitalReading → FHIR-R4-Observation. Für die gemappten Vitalarten ÜLB-/KBV-konform
 * (KBV_PR_MIO_ULB_Observation_<Vital>): SNOMED+LOINC-Coding, zweiter Vitalzeichen-Category-Slice,
 * Pflicht-performer. Codes/Werte aus den kbv.basis-Beispielen verifiziert. Temperatur + Schmerz bleiben
 * generisch (kein konformes Profil-Mapping: Temperatur ohne Beispiel, Schmerz numerisch statt CodeableConcept).
 */
class ObservationMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    private const LOINC = 'http://loinc.org';

    private const LOINC_VERSION = '2.72';

    private const UCUM = 'http://unitsofmeasure.org';

    /** Vitalart → [Profil, SNOMED-code, SNOMED-display, LOINC-code, LOINC-display, Einheit-Anzeige, UCUM-code]. */
    private const VITALS = [
        'gewicht' => ['Body_Weight', '27113001', 'Body weight (observable entity)', '29463-7', 'Body weight', 'kg', 'kg'],
        'koerpergroesse' => ['Body_Height', '50373000', 'Body height (observable entity)', '8302-2', 'Body height', 'cm', 'cm'],
        'puls' => ['Heart_Rate', '364075005', 'Heart rate (observable entity)', '8867-4', 'Heart rate', 'per minute', '/min'],
        'spo2' => ['Peripheral_Oxygen_Saturation', '431314004', 'Peripheral oxygen saturation (observable entity)', '2708-6', 'Oxygen saturation in Arterial blood', '%', '%'],
        'atemfrequenz' => ['Respiratory_Rate', '86290005', 'Respiratory rate (observable entity)', '9279-1', 'Respiratory rate', 'per minute', '/min'],
        'blutzucker' => ['Glucose_Concentration', '434912009', 'Blood glucose concentration (observable entity)', '2339-0', 'Glucose [Mass/volume] in Blood', 'mg/dL', 'mg/dL'],
    ];

    public static function id(VitalReading $v): string
    {
        return 'observation-'.$v->id;
    }

    /** @return array<string, mixed> */
    public function map(VitalReading $v, string $patientReference, ?string $performerReference = null): array
    {
        $typ = $v->typ->value;

        if ($typ === VitalType::Blutdruck->value) {
            return $this->bloodPressure($v, $patientReference, $performerReference);
        }
        if (isset(self::VITALS[$typ])) {
            return $this->vital($v, self::VITALS[$typ], $patientReference, $performerReference);
        }

        return $this->generic($v, $patientReference);
    }

    /**
     * @param  array<int, string>  $def
     * @return array<string, mixed>
     */
    private function vital(VitalReading $v, array $def, string $patientReference, ?string $performerReference): array
    {
        [$profile, $sctCode, $sctDisplay, $loincCode, $loincDisplay, $unit, $ucum] = $def;

        $obs = $this->scaffold($profile, $patientReference, $v, $performerReference);
        $obs['code'] = ['coding' => [$this->snomed($sctCode, $sctDisplay), $this->loinc($loincCode, $loincDisplay)]];
        $obs['valueQuantity'] = $this->quantity((float) $v->wert, $unit, $ucum);

        return $obs;
    }

    /** @return array<string, mixed> */
    private function bloodPressure(VitalReading $v, string $patientReference, ?string $performerReference): array
    {
        $obs = $this->scaffold('Blood_Pressure', $patientReference, $v, $performerReference);
        $obs['code'] = ['coding' => [
            $this->snomed('75367002', 'Blood pressure (observable entity)'),
            $this->loinc('85354-9', 'Blood pressure panel with all children optional'),
        ]];
        $obs['component'] = [
            $this->bpComponent('271649006', 'Systolic blood pressure (observable entity)', '8480-6', 'Systolic blood pressure', (float) $v->wert),
            $this->bpComponent('271650006', 'Diastolic blood pressure (observable entity)', '8462-4', 'Diastolic blood pressure', (float) $v->wert2),
        ];

        return $obs;
    }

    /** @return array<string, mixed> */
    private function bpComponent(string $sct, string $sctDisplay, string $loinc, string $loincDisplay, float $value): array
    {
        return [
            'code' => ['coding' => [$this->snomed($sct, $sctDisplay), $this->loinc($loinc, $loincDisplay)]],
            'valueQuantity' => $this->quantity($value, 'mm Hg', 'mm[Hg]'),
        ];
    }

    /**
     * Gemeinsames Gerüst eines konformen Vital-Observation (ohne code/value).
     *
     * @return array<string, mixed>
     */
    private function scaffold(string $profile, string $patientReference, VitalReading $v, ?string $performerReference): array
    {
        $obs = [
            'resourceType' => 'Observation',
            'id' => self::id($v),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_'.$profile.'|1.0.0']],
            'status' => 'final',
            'category' => [
                ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'vital-signs']]],
                ['coding' => [$this->snomed('1184593002', 'Vital sign document section (record artifact)')]],
            ],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $v->gemessen_am->toIso8601String(),
        ];
        if ($performerReference !== null) {
            $obs['performer'] = [['reference' => $performerReference]];
        }

        return $obs;
    }

    /** Generisches Observation für ungemappte Vitalarten (Temperatur, Schmerz) — kein ÜLB-Profil. */
    /** @return array<string, mixed> */
    private function generic(VitalReading $v, string $patientReference): array
    {
        [$loinc, $display, $ucum] = match ($v->typ) {
            VitalType::Temperatur => ['8310-5', 'Körpertemperatur', 'Cel'],
            VitalType::Schmerz => ['72514-3', 'Schmerzstärke (NRS 0–10)', null],
            default => ['', (string) $v->typ->value, null],
        };

        return [
            'resourceType' => 'Observation',
            'id' => self::id($v),
            'status' => 'final',
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'vital-signs']]]],
            'code' => ['coding' => [['system' => self::LOINC, 'code' => $loinc, 'display' => $display]], 'text' => $display],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $v->gemessen_am->toIso8601String(),
            'valueQuantity' => $this->quantity((float) $v->wert, $v->einheit ?: '', $ucum),
        ];
    }

    /** @return array<string, mixed> */
    private function snomed(string $code, string $display): array
    {
        return ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => $code, 'display' => $display];
    }

    /** @return array<string, mixed> */
    private function loinc(string $code, string $display): array
    {
        return ['system' => self::LOINC, 'version' => self::LOINC_VERSION, 'code' => $code, 'display' => $display];
    }

    /** @return array<string, mixed> */
    private function quantity(float $value, string $unit, ?string $ucum): array
    {
        $q = ['value' => $value, 'unit' => $unit !== '' ? $unit : ($ucum ?? 'Punkte')];
        if ($ucum !== null) {
            $q['system'] = self::UCUM;
            $q['code'] = $ucum;
        }

        return $q;
    }
}
