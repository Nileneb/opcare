<?php

namespace App\Domains\Medication\Enums;

enum VitalType: string
{
    case Blutdruck = 'blutdruck';
    case Puls = 'puls';
    case Temperatur = 'temperatur';
    case Gewicht = 'gewicht';
    case Blutzucker = 'blutzucker';
    case Schmerz = 'schmerz';
    case SpO2 = 'spo2';
    case Atemfrequenz = 'atemfrequenz';

    public function einheit(): string
    {
        return match ($this) {
            self::Blutdruck => 'mmHg', self::Puls => '/min', self::Temperatur => '°C',
            self::Gewicht => 'kg', self::Blutzucker => 'mg/dl', self::Schmerz => 'NRS 0–10',
            self::SpO2 => '%', self::Atemfrequenz => '/min',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Blutdruck => 'Blutdruck', self::Puls => 'Puls', self::Temperatur => 'Temperatur',
            self::Gewicht => 'Gewicht', self::Blutzucker => 'Blutzucker', self::Schmerz => 'Schmerz',
            self::SpO2 => 'Sauerstoffsättigung', self::Atemfrequenz => 'Atemfrequenz',
        };
    }
}
