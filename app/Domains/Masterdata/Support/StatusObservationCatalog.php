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
        ];
    }

    public static function get(string $typ): ?array
    {
        return self::all()[$typ] ?? null;
    }
}
