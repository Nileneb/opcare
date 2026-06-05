<?php

namespace App\Domains\Masterdata\Support;

/**
 * Config-getriebener Katalog der codierten ÜLB-Status-Observations (kbv.mio.ueberleitungsbogen 1.0.0).
 * Einzige Stelle, an der Typ → FHIR-Code (SNOMED), ÜLB-Sektion, Wert-Art und erlaubte Werte definiert sind.
 * Sowohl UI (Auswahl) als auch FHIR-Mapper lesen daraus.
 */
class StatusObservationCatalog
{
    public const SNOMED = 'http://snomed.info/sct';

    /**
     * @return array<string, array{label:string, section:string, kind:string, code:array{0:string,1:string}, options:array<string,string>}>
     */
    public static function all(): array
    {
        return [
            'bewusstsein' => [
                'label' => 'Bewusstseinslage', 'section' => 'Bewusstsein / Orientierung', 'kind' => 'coded',
                'code' => ['312012004', 'Bewusstsein'],
                'options' => ['271591004' => 'wach', '271782001' => 'benommen/schläfrig', '274659008' => 'soporös', '371632003' => 'komatös'],
            ],
            'harnkontinenz' => [
                'label' => 'Harnkontinenz', 'section' => 'Kontinenz', 'kind' => 'coded',
                'code' => ['129009001', 'Harnkontinenz'],
                'options' => ['45850009' => 'kontinent', '450841000' => 'intermittierend inkontinent', '129853007' => 'vollständig inkontinent'],
            ],
            'stuhlkontinenz' => [
                'label' => 'Stuhlkontinenz', 'section' => 'Kontinenz', 'kind' => 'coded',
                'code' => ['16490003', 'Stuhlkontinenz'],
                'options' => ['24029004' => 'kontinent', '165230005' => 'gelegentlich inkontinent', '72042002' => 'inkontinent'],
            ],
            'kostform' => [
                'label' => 'Kostform', 'section' => 'Ernährung', 'kind' => 'coded',
                'code' => ['230092000', 'Kostform'],
                'options' => ['160670007' => 'Diabeteskost', '16208003' => 'fettarm', '386619000' => 'natriumarm', '14627000' => 'eiweißreich', '10888001' => 'passiert/flüssig', '437651000124104' => 'glutenfrei'],
            ],
            'ernaehrungsform' => [
                'label' => 'Ernährungsform', 'section' => 'Ernährung', 'kind' => 'coded',
                'code' => ['129007004', 'Ernährungsform'],
                'options' => ['61420007' => 'Sondenernährung', '225373002' => 'PEG', '225372007' => 'parenteral (total)'],
            ],
            'atmung' => [
                'label' => 'Atmung (qualitativ)', 'section' => 'Atmung', 'kind' => 'text',
                'code' => ['78064003', 'Atmung'],
                'options' => [],
            ],
        ];
    }

    public static function get(string $typ): ?array
    {
        return self::all()[$typ] ?? null;
    }
}
