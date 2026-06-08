<?php

namespace App\Domains\Masterdata\Support;

/**
 * Config-getriebener Katalog der codierten ÜLB-Status-Observations (kbv.mio.ueberleitungsbogen 1.0.0).
 * Einzige Stelle, an der Typ → FHIR-Profil, fixe code.coding, ÜLB-Composition-Sektion, Wert-Art und
 * erlaubte Werte definiert sind. UI (`ResidentShow`) liest `label`/`section`/`options`; der FHIR-Mapper
 * (`StatusObservationMapper`) liest `profile`/`slice`/`fhir_code`/`value_displays`.
 */
class StatusObservationCatalog
{
    public const SNOMED = 'http://snomed.info/sct';

    public const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    /**
     * @return array<string, array{
     *     label:string, section:string, kind:string, code:array{0:string,1:string},
     *     profile?:string, slice?:string, fhir_code?:array{0:string,1:string},
     *     options:array<int|string,string>, value_displays?:array<int|string,string>
     * }>
     */
    public static function all(): array
    {
        return [
            'bewusstsein' => [
                'label' => 'Bewusstseinslage', 'section' => 'Bewusstsein / Orientierung', 'kind' => 'coded',
                'code' => ['312012004', 'Bewusstsein'],
                'profile' => 'Observation_Cognitive_Awareness', 'slice' => 'orientierungPsyche',
                'fhir_code' => ['312012004', 'Cognitive function: awareness (observable entity)'],
                'options' => ['271591004' => 'wach', '271782001' => 'benommen/schläfrig', '274659008' => 'soporös', '371632003' => 'komatös'],
                'value_displays' => ['271591004' => 'Fully conscious (finding)', '271782001' => 'Drowsy (finding)', '274659008' => 'Semicoma (disorder)', '371632003' => 'Coma (disorder)'],
            ],
            'harnkontinenz' => [
                'label' => 'Harnkontinenz', 'section' => 'Kontinenz', 'kind' => 'coded',
                'code' => ['129009001', 'Harnkontinenz'],
                'profile' => 'Observation_Urinary_Continence_Differentiated_Assessment', 'slice' => 'harnkontinenzDifferenzierteEinschaetzung',
                'fhir_code' => ['129009001', 'Bladder control, function (observable entity)'],
                'options' => ['45850009' => 'kontinent', '450841000' => 'intermittierend inkontinent', '129853007' => 'vollständig inkontinent'],
                'value_displays' => ['45850009' => 'Continent of urine (finding)', '450841000' => 'Intermittent urinary incontinence (finding)', '129853007' => 'Total urinary incontinence (finding)'],
            ],
            'stuhlkontinenz' => [
                'label' => 'Stuhlkontinenz', 'section' => 'Kontinenz', 'kind' => 'coded',
                'code' => ['16490003', 'Stuhlkontinenz'],
                'profile' => 'Observation_Fecal_Continence_Differentiated_Assessment', 'slice' => 'stuhlkontinenzDifferenzierteEinschaetzung',
                'fhir_code' => ['16490003', 'Anorectal continence, function (observable entity)'],
                'options' => ['24029004' => 'kontinent', '165230005' => 'gelegentlich inkontinent', '72042002' => 'inkontinent'],
                'value_displays' => ['24029004' => 'Bowels: fully continent (finding)', '165230005' => 'Bowels: occasional accident (finding)', '72042002' => 'Incontinence of feces (finding)'],
            ],
            'kostform' => [
                'label' => 'Kostform', 'section' => 'Ernährung', 'kind' => 'coded',
                'code' => ['230092000', 'Kostform'],
                // Ernährung wird als Presence-Sektion (ernaehrung) aggregiert exportiert — die konkrete
                // Kostform/Form hat im ÜLB-Nutrition-ValueSet (nur present/absent) keinen codierten Slot;
                // Detail steht im Composition-Narrativ. Daher kein eigenes per-Eintrag-`profile`/`slice`.
                'nutrition' => true,
                'options' => ['160670007' => 'Diabeteskost', '16208003' => 'fettarm', '386619000' => 'natriumarm', '14627000' => 'eiweißreich', '10888001' => 'passiert/flüssig', '437651000124104' => 'glutenfrei'],
            ],
            'ernaehrungsform' => [
                'label' => 'Ernährungsform', 'section' => 'Ernährung', 'kind' => 'coded',
                'code' => ['129007004', 'Ernährungsform'],
                'nutrition' => true,
                'options' => ['61420007' => 'Sondenernährung', '225373002' => 'PEG', '225372007' => 'parenteral (total)'],
            ],
            'atmung' => [
                'label' => 'Atmung (qualitativ)', 'section' => 'Atmung', 'kind' => 'text',
                'code' => ['78064003', 'Atmung'],
                'profile' => 'Observation_Qualitative_Description_Breathing', 'slice' => 'qualitativeBeschreibungAtmung',
                'fhir_code' => ['78064003:370132008=26716007', 'Respiratory function (observable entity) : Scale type (attribute) = Qualitative (qualifier value)'],
                'options' => [],
            ],
            'atemwegszugang' => [
                'label' => 'Atemwegszugang', 'section' => 'Atmung', 'kind' => 'coded',
                'code' => ['313292002', 'Atemwegszugang'],
                'profile' => 'Observation_Respiratory_Access', 'slice' => 'atemwegszugang',
                'fhir_code' => ['313292002', 'Route of breathing (observable entity)'],
                'effective' => 'period',
                'options' => ['366141005' => 'natürliche Atemwege', '302108003' => 'Tracheostoma'],
                'value_displays' => ['366141005' => 'Finding of route of breathing (finding)', '302108003' => 'Tracheostomy present (finding)'],
            ],
            'atmungsunterstuetzung' => [
                'label' => 'Atmungsunterstützung', 'section' => 'Atmung', 'kind' => 'coded',
                'code' => ['40617009', 'Atmungsunterstützung'],
                'profile' => 'Observation_Respiratory_Support', 'slice' => 'atmungsunterstuetzung',
                'fhir_code' => ['363787002:704321009=40617009', 'Observable entity (observable entity) : Characterizes (attribute) = Artificial respiration (procedure)'],
                'options' => ['106048009:47429007=40617009,363713009=52101004' => 'Beatmung vorhanden', '106048009:47429007=40617009,363713009=2667000' => 'keine Beatmung'],
                'value_displays' => [
                    '106048009:47429007=40617009,363713009=52101004' => 'Respiratory finding (finding) : Associated with (attribute) = Artificial respiration (procedure) , Has interpretation (attribute) = Present (qualifier value)',
                    '106048009:47429007=40617009,363713009=2667000' => 'Respiratory finding (finding) : Associated with (attribute) = Artificial respiration (procedure) , Has interpretation (attribute) = Absent (qualifier value)',
                ],
            ],
            'raeumliche_isolation' => [
                'label' => 'Räumliche Isolation', 'section' => 'Isolation', 'kind' => 'coded',
                'code' => ['40174006', 'Räumliche Isolation'],
                'profile' => 'Observation_Isolation_Necessary', 'slice' => 'raeumlicheIsolation',
                'fhir_code' => ['363787002:704321009=40174006', 'Observable entity (observable entity) : Characterizes (attribute) = Isolation procedure (procedure)'],
                'options' => [
                    '129125009:363589002=40174006,408730004=897015005' => 'Isolation empfohlen',
                    '129125009:363589002=40174006,408730004=897016006' => 'Isolation nicht empfohlen',
                    '129125009:363589002=40174006,408730004=410537005' => 'unklar',
                ],
                'value_displays' => [
                    '129125009:363589002=40174006,408730004=897015005' => 'Procedure with explicit context (situation) : Associated procedure (attribute) = Isolation procedure (procedure) , Procedure context (attribute) = Recommended (qualifier value)',
                    '129125009:363589002=40174006,408730004=897016006' => 'Procedure with explicit context (situation) : Associated procedure (attribute) = Isolation procedure (procedure) , Procedure context (attribute) = Not recommended (qualifier value)',
                    '129125009:363589002=40174006,408730004=410537005' => 'Procedure with explicit context (situation) : Associated procedure (attribute) = Isolation procedure (procedure) , Procedure context (attribute) = Action status unknown (qualifier value)',
                ],
            ],
            'patientenwunsch' => [
                'label' => 'Patientenwunsch', 'section' => 'Patientenwunsch', 'kind' => 'codeable_text',
                'code' => ['1186606009', 'Patientenwunsch'],
                'profile' => 'Observation_Wish', 'slice' => 'patientenwunsch',
                'fhir_code' => ['1186606009', 'Patient request observable (observable entity)'],
                'options' => [],
            ],
            'auffaelliges_verhalten' => [
                'label' => 'Auffälliges Verhalten', 'section' => 'Verhalten', 'kind' => 'codeable_text',
                'code' => ['25786006', 'Auffälliges Verhalten'],
                'profile' => 'Observation_Striking_Behavior', 'slice' => 'auffaelligesVerhalten',
                'fhir_code' => ['363896009:704326004=25786006', 'Behavior observable (observable entity) : Precondition (attribute) = Abnormal behavior (finding)'],
                'options' => [],
            ],
            'zeitpunkt_letzte_miktion' => [
                'label' => 'Zeitpunkt der letzten Miktion', 'section' => 'Kontinenz', 'kind' => 'datetime',
                'code' => ['364201005', 'Letzte Miktion'],
                'profile' => 'Observation_Last_Micturition', 'slice' => 'zeitpunktLetzteMiktion',
                'fhir_code' => ['364201005:370134009=57615005', 'Urine output observable (observable entity) : Time aspect (attribute) = Definite time (qualifier value)'],
                'options' => [],
            ],
            'zeitpunkt_letzter_stuhlgang' => [
                'label' => 'Zeitpunkt des letzten Stuhlgangs', 'section' => 'Kontinenz', 'kind' => 'datetime',
                'code' => ['364171004', 'Letzter Stuhlgang'],
                'profile' => 'Observation_Last_Bowel_Movement', 'slice' => 'zeitpunktLetzterStuhlgang',
                'fhir_code' => ['364171004:370134009=57615005', 'Defecation observable (observable entity) : Time aspect (attribute) = Definite time (qualifier value)'],
                'options' => [],
            ],
            'grad_der_behinderung' => [
                'label' => 'Grad der Behinderung', 'section' => 'Soziales', 'kind' => 'coded',
                'code' => ['21134002', 'Grad der Behinderung'],
                'profile' => 'Observation_Degree_Of_Disability_Available', 'slice' => 'gradDerBehinderung',
                'fhir_code' => ['363787002:704326004=(404684003:363713009=260411009,47429007=(21134002:363713009=272520006))', 'Observable entity (observable entity) : Precondition (attribute) = ( Clinical finding (finding) : Has interpretation (attribute) = Presence findings (qualifier value) , Associated with (attribute) = ( Disability (finding) : Has interpretation (attribute) = Degree findings (qualifier value) ) )'],
                'options' => [
                    '404684003:363713009=52101004,47429007=(21134002:363713009=272520006)' => 'Behinderung vorhanden',
                    '404684003:363713009=2667000,47429007=(21134002:363713009=272520006)' => 'keine Behinderung',
                    '404684003:363713009=373068000,47429007=(21134002:363713009=272520006)' => 'unbekannt',
                ],
                'value_displays' => [
                    '404684003:363713009=52101004,47429007=(21134002:363713009=272520006)' => 'Clinical finding (finding) : Has interpretation (attribute) = Present (qualifier value) , Associated with (attribute) = ( Disability (finding) : Has interpretation (attribute) = Degree findings (qualifier value) )',
                    '404684003:363713009=2667000,47429007=(21134002:363713009=272520006)' => 'Clinical finding (finding) : Has interpretation (attribute) = Absent (qualifier value) , Associated with (attribute) = ( Disability (finding) : Has interpretation (attribute) = Degree findings (qualifier value) )',
                    '404684003:363713009=373068000,47429007=(21134002:363713009=272520006)' => 'Clinical finding (finding) : Has interpretation (attribute) = Undetermined (qualifier value) , Associated with (attribute) = ( Disability (finding) : Has interpretation (attribute) = Degree findings (qualifier value) )',
                ],
            ],
        ];
    }

    public static function get(string $typ): ?array
    {
        return self::all()[$typ] ?? null;
    }
}
