<?php

namespace App\Domains\Scheduling\Compliance\Enums;

/**
 * § 4 ArbZG-Status einer Ist-Zeitbuchung: ausgehend von der Brutto-Arbeitszeit und der erfassten Pause.
 * Macht § 4 auf der tatsächlich erfassten Zeit prüfbar (die Pause IST erfasst — `pause_minuten`).
 */
enum Pausenstatus: string
{
    case Laeuft = 'laeuft';
    case NichtRelevant = 'nicht_relevant';
    case Konform = 'konform';
    case Unzureichend = 'unzureichend';

    public function label(): string
    {
        return match ($this) {
            self::Laeuft => 'läuft',
            self::NichtRelevant => '§ 4 n/a',
            self::Konform => '§ 4 ✓',
            self::Unzureichend => '§ 4 Pause zu kurz',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Laeuft => 'gray',
            self::NichtRelevant => 'gray',
            self::Konform => 'green',
            self::Unzureichend => 'red',
        };
    }
}
