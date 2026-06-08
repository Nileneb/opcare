<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Collection;

/**
 * KBV_PR_MIO_ULB_Composition (Pflegeüberleitung). Sektions-Slicing ist CLOSED + per code.coding
 * diskriminiert: jede Sektion muss exakt einem definierten Slice mit fixem Code entsprechen, section.text
 * ist verboten (Verlauf → Composition.text). Pflicht-Sektion: pflegegrad.
 */
class CompositionMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    /** Slice-Name → [section.code-Coding, Sektions-Titel]. Codes/Displays aus dem ÜLB-Composition-Profil. */
    private const SECTIONS = [
        'pflegegrad' => [
            ['system' => 'https://fhir.kbv.de/CodeSystem/KBV_CS_MIO_ULB_Section_Codes', 'version' => '1.0.0', 'code' => 'SectionPflegegrad', 'display' => 'Bereich Pflegegrad'],
            'Pflegegrad',
        ],
        'vitalparameter' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '1184593002', 'display' => 'Vital sign document section (record artifact)'],
            'Vitalzeichen und Körpermaße',
        ],
        'allergienUndUnvertraeglichkeiten' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '722446000', 'display' => 'Allergy record (record artifact)'],
            'Allergien und Unverträglichkeiten',
        ],
        'medizinprodukte' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '1184586001', 'display' => 'Medical device document section (record artifact)'],
            'Medizinprodukte',
        ],
        'patientenAdressbuch' => [
            ['system' => 'https://fhir.kbv.de/CodeSystem/KBV_CS_MIO_ULB_Address_Book', 'version' => '1.0.0', 'code' => 'Kontakt_und_behandelnde_Personen', 'display' => 'Kontakt- und behandelnde Personen'],
            'Patienten-Adressbuch',
        ],
        'orientierungPsyche' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '43173001+312012004', 'display' => 'Orientation , function (observable entity) + Cognitive function : awareness (observable entity)'],
            'Orientierung / Psyche',
        ],
        'ernaehrung' => [
            ['system' => 'http://loinc.org', 'version' => '2.72', 'code' => '34801-1', 'display' => 'Nutrition and dietetics Note'],
            'Ernährung',
        ],
        'qualitativeBeschreibungAtmung' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '78064003:370132008=26716007', 'display' => 'Respiratory function (observable entity) : Scale type (attribute) = Qualitative (qualifier value)'],
            'Qualitative Beschreibung der Atmung',
        ],
        'atemwegszugang' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '313292002', 'display' => 'Route of breathing (observable entity)'],
            'Atemwegszugang',
        ],
        'atmungsunterstuetzung' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '363787002:704321009=40617009', 'display' => 'Observable entity (observable entity) : Characterizes (attribute) = Artificial respiration (procedure)'],
            'Atmungsunterstützung',
        ],
        // WHY: Titel exakt aus dem KBV-Profil übernommen — der Tippfehler „Isoation" steht so im
        // ÜLB-Profil (KBV_PR_MIO_ULB_Composition); abweichender Titel würde der Validator als Fehler werten.
        'raeumlicheIsolation' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '363787002:704321009=40174006', 'display' => 'Observable entity (observable entity) : Characterizes (attribute) = Isolation procedure (procedure)'],
            'Notwendigkeit der räumlichen Isoation',
        ],
        'patientenwunsch' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '1186606009', 'display' => 'Patient request observable (observable entity)'],
            'Patientenwunsch',
        ],
        'auffaelligesVerhalten' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '363896009:704326004=25786006', 'display' => 'Behavior observable (observable entity) : Precondition (attribute) = Abnormal behavior (finding)'],
            'Auffälliges Verhalten',
        ],
        'gradDerBehinderung' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '363787002:704326004=21134002', 'display' => 'Observable entity (observable entity) : Precondition (attribute) = Disability (finding)'],
            'Grad der Behinderung',
        ],
        'harnkontinenzDifferenzierteEinschaetzung' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '129009001', 'display' => 'Bladder control, function (observable entity)'],
            'Harnkontinenz differenzierte Einschätzung',
        ],
        'stuhlkontinenzDifferenzierteEinschaetzung' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '16490003', 'display' => 'Anorectal continence, function (observable entity)'],
            'Stuhlkontinenz differenzierte Einschätzung',
        ],
        'medikationsplan' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '736378000', 'display' => 'Medication management plan (record artifact)'],
            'Medikationsplan',
        ],
        'funktionsbeurteilungen' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '1184588000', 'display' => 'Functional status document section (record artifact)'],
            'Funktionsbeurteilungen',
        ],
        'probleme' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '1184595009', 'display' => 'Present problem document section (record artifact)'],
            'Probleme',
        ],
        'pflegerischeMassnahme' => [
            ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => '9632001', 'display' => 'Nursing procedure (procedure)'],
            'Pflegerische Maßnahme',
        ],
    ];

    public static function id(Resident $r): string
    {
        return 'composition-'.$r->id;
    }

    /**
     * @param  array<int, array{slice: string, entries: array<int, string>}>  $sections
     * @param  Collection<int, CareReport>  $reports
     * @return array<string, mixed>
     */
    public function map(
        Resident $r,
        string $patientReference,
        string $date,
        string $authorReference,
        array $sections,
        Collection $reports,
    ): array {
        $section = [];
        foreach ($sections as $s) {
            if ($s['entries'] === [] || ! isset(self::SECTIONS[$s['slice']])) {
                continue;
            }
            [$coding, $title] = self::SECTIONS[$s['slice']];
            $section[] = [
                'title' => $title,
                'code' => ['coding' => [$coding]],
                'entry' => array_map(fn (string $ref) => ['reference' => $ref], $s['entries']),
            ];
        }

        return [
            'resourceType' => 'Composition',
            'id' => self::id($r),
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Composition|1.0.0']],
            'text' => ['status' => 'extensions', 'div' => $this->narrative($reports)],
            'status' => 'final',
            'type' => ['coding' => [[
                'system' => self::SNOMED, 'version' => self::SNOMED_VERSION,
                'code' => '721919000', 'display' => 'Nurse discharge summary (record artifact)',
            ]]],
            'subject' => ['reference' => $patientReference],
            'date' => $date,
            'author' => [['reference' => $authorReference]],
            'title' => 'Überleitungsbogen',
            'section' => $section,
        ];
    }

    /** @param Collection<int, CareReport> $reports */
    private function narrative(Collection $reports): string
    {
        $rows = $reports
            ->map(fn ($rep) => '<p><b>'.e($rep->datum->format('d.m.Y H:i')).' ('.e($rep->schicht->value).')</b>: '.e($rep->text).'</p>')
            ->implode('');

        if ($rows === '') {
            $rows = '<p>Pflegeüberleitungsbogen — keine Verlaufseinträge erfasst.</p>';
        }

        return '<div xmlns="http://www.w3.org/1999/xhtml">'.$rows.'</div>';
    }
}
