<?php

namespace App\Domains\Assessment\Support;

use App\Domains\Assessment\Enums\ScaleDirection;
use App\Domains\CarePlanning\Enums\RiskType;

class InstrumentReferenceData
{
    /**
     * @return array<int, array{
     *   name:string, risk_type:RiskType, direction:ScaleDirection, intervall_tage:int,
     *   risk_bands:array, items:array<int, array{label:string, options:array<int, array{label:string, punkte:int}>}>
     * }>
     */
    public static function instruments(): array
    {
        return [self::braden(), self::sturz(), self::besd()];
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
            foreach (array_values($stufen) as $i => $stufe) {
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
