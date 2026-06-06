<?php

namespace App\Domains\Assessment\Support;

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;

class InstrumentReferenceData
{
    /**
     * @return array<int, array{
     *   name:string, loinc?:string, risk_type:RiskType, direction:ScaleDirection, intervall_tage:int,
     *   risk_bands:array, items:array<int, array{label:string, loinc?:string, options:array<int, array{label:string, punkte:int}>}>
     * }>
     */
    public static function instruments(): array
    {
        return [self::braden(), self::sturz(), self::besd(), self::barthel()];
    }

    private static function barthel(): array
    {
        // Barthel-Index (ADL): 10 Items, Summe 0–100. Höher = selbstständiger → LowerIsWorse.
        // LOINC-Codes je Item + Summe aus dem ÜLB-MIO (KBV_PR_MIO_ULB_Observation_Barthel_*).
        $p = fn (array $stufen) => array_map(fn ($s, $i) => ['label' => $s[0], 'punkte' => $s[1]], $stufen, array_keys($stufen));
        $items = [
            ['label' => 'Essen', 'loinc' => '83184-2', 'options' => $p([['unfähig', 0], ['Hilfe nötig', 5], ['selbstständig', 10]])],
            ['label' => 'Bett-/(Roll-)Stuhl-Transfer', 'loinc' => '83185-9', 'options' => $p([['nicht möglich', 0], ['erhebliche Hilfe', 5], ['geringe Hilfe', 10], ['selbstständig', 15]])],
            ['label' => 'Waschen / Körperpflege', 'loinc' => '96767-9', 'options' => $p([['Hilfe nötig', 0], ['selbstständig', 5]])],
            ['label' => 'Toilettenbenutzung', 'loinc' => '83183-4', 'options' => $p([['abhängig', 0], ['Hilfe nötig', 5], ['selbstständig', 10]])],
            ['label' => 'Baden / Duschen', 'loinc' => '83181-8', 'options' => $p([['Hilfe nötig', 0], ['selbstständig', 5]])],
            ['label' => 'Aufstehen & Gehen', 'loinc' => '83186-7', 'options' => $p([['immobil', 0], ['Rollstuhl selbstständig', 5], ['Gehen mit Hilfe', 10], ['selbstständig > 50 m', 15]])],
            ['label' => 'Treppensteigen', 'loinc' => '96758-8', 'options' => $p([['nicht möglich', 0], ['mit Hilfe', 5], ['selbstständig', 10]])],
            ['label' => 'An-/Auskleiden', 'loinc' => '83182-6', 'options' => $p([['abhängig', 0], ['Hilfe nötig', 5], ['selbstständig', 10]])],
            ['label' => 'Stuhlkontinenz', 'loinc' => '96759-6', 'options' => $p([['inkontinent', 0], ['gelegentlich inkontinent', 5], ['kontinent', 10]])],
            ['label' => 'Harnkontinenz', 'loinc' => '96760-4', 'options' => $p([['inkontinent', 0], ['gelegentlich inkontinent', 5], ['kontinent', 10]])],
        ];

        return [
            'name' => 'Barthel-Index',
            'loinc' => '96761-2', // Total_Barthel_Index
            'risk_type' => RiskType::Mobilitaet,
            'direction' => ScaleDirection::LowerIsWorse,
            'intervall_tage' => 90,
            // Mahoney-Barthel Abhängigkeitsgrade (0–100)
            'risk_bands' => [
                ['band' => 'sehr_hoch', 'min' => null, 'max' => 20],
                ['band' => 'hoch', 'min' => 21, 'max' => 60],
                ['band' => 'mittel', 'min' => 61, 'max' => 90],
                ['band' => 'gering', 'min' => 91, 'max' => 99],
                ['band' => 'kein', 'min' => 100, 'max' => null],
            ],
            'items' => $items,
        ];
    }

    private static function braden(): array
    {
        // 6 Items je 1–4 Punkte; niedriger Gesamtscore = höheres Risiko.
        $skalen = [
            'Sensorisches Empfindungsvermögen' => ['fehlt', 'stark eingeschränkt', 'leicht eingeschränkt', 'vorhanden'],
            'Feuchtigkeit' => ['ständig feucht', 'oft feucht', 'manchmal feucht', 'selten feucht'],
            'Aktivität' => ['bettlägerig', 'sitzt auf', 'geht wenig', 'geht regelmäßig'],
            'Mobilität' => ['komplett immobil', 'stark eingeschränkt', 'gering eingeschränkt', 'mobil'],
            'Ernährung' => ['sehr schlecht', 'mäßig', 'ausreichend', 'gut'],
            'Reibung/Scherkräfte' => ['Problem', 'potenzielles Problem', 'kein Problem', 'kein Problem'],
        ];
        $items = [];
        foreach ($skalen as $label => $stufen) {
            $options = [];
            foreach ($stufen as $i => $stufe) {
                $options[] = ['label' => $stufe, 'punkte' => $i + 1];
            }
            $items[] = ['label' => $label, 'options' => $options];
        }

        return [
            'name' => 'Braden-Skala',
            'risk_type' => RiskType::Dekubitus,
            'direction' => ScaleDirection::LowerIsWorse,
            'intervall_tage' => 90,
            'risk_bands' => [
                ['band' => 'sehr_hoch', 'min' => null, 'max' => 9],
                ['band' => 'hoch', 'min' => 10, 'max' => 12],
                ['band' => 'mittel', 'min' => 13, 'max' => 14],
                ['band' => 'gering', 'min' => 15, 'max' => 18],
                ['band' => 'kein', 'min' => 19, 'max' => null],
            ],
            'items' => $items,
        ];
    }

    private static function sturz(): array
    {
        // Risikofaktoren-Checkliste: je zutreffend = Punkte; höherer Score = höheres Risiko.
        $faktoren = [
            'Sturz in den letzten 12 Monaten' => 2,
            'Gang-/Standunsicherheit' => 2,
            'Sehbeeinträchtigung' => 1,
            'Psychopharmaka / sedierende Medikation' => 1,
            'Kognitive Einschränkung / Desorientiertheit' => 1,
            'Inkontinenz / häufiger Toilettengang' => 1,
        ];
        $items = [];
        foreach ($faktoren as $label => $p) {
            $items[] = ['label' => $label, 'options' => [
                ['label' => 'nein', 'punkte' => 0],
                ['label' => 'ja', 'punkte' => $p],
            ]];
        }

        return [
            'name' => 'Sturzrisiko-Checkliste',
            'risk_type' => RiskType::Sturz,
            'direction' => ScaleDirection::HigherIsWorse,
            'intervall_tage' => 90,
            'risk_bands' => [
                ['band' => 'gering', 'min' => null, 'max' => 1],
                ['band' => 'mittel', 'min' => 2, 'max' => 3],
                ['band' => 'hoch', 'min' => 4, 'max' => null],
            ],
            'items' => $items,
        ];
    }

    private static function besd(): array
    {
        // BESD (Schmerzbeurteilung bei Demenz): 5 Items je 0–2 Punkte; höherer Score = mehr Schmerz.
        $items = [];
        foreach (['Atmung', 'Negative Lautäußerung', 'Gesichtsausdruck', 'Körpersprache', 'Trost'] as $label) {
            $items[] = ['label' => $label, 'options' => [
                ['label' => 'normal/0', 'punkte' => 0],
                ['label' => 'leicht/1', 'punkte' => 1],
                ['label' => 'deutlich/2', 'punkte' => 2],
            ]];
        }

        return [
            'name' => 'BESD-Schmerzskala',
            'risk_type' => RiskType::Schmerz,
            'direction' => ScaleDirection::HigherIsWorse,
            'intervall_tage' => 30,
            'risk_bands' => [
                ['band' => 'kein', 'min' => null, 'max' => 1],
                ['band' => 'gering', 'min' => 2, 'max' => 3],
                ['band' => 'mittel', 'min' => 4, 'max' => 6],
                ['band' => 'hoch', 'min' => 7, 'max' => null],
            ],
            'items' => $items,
        ];
    }
}
