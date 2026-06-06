<?php

namespace App\Domains\Accounting\Enums;

/**
 * Kontoart der doppelten Buchführung. Aktiv/Aufwand mehren sich im Soll, Passiv/Ertrag im Haben —
 * davon hängt die Richtung des Saldos ab.
 */
enum KontoTyp: string
{
    case Aktiv = 'aktiv';
    case Passiv = 'passiv';
    case Aufwand = 'aufwand';
    case Ertrag = 'ertrag';

    public function label(): string
    {
        return match ($this) {
            self::Aktiv => 'Aktivkonto (Bestand)',
            self::Passiv => 'Passivkonto (Kapital/Schulden)',
            self::Aufwand => 'Aufwandskonto',
            self::Ertrag => 'Ertragskonto',
        };
    }

    /** Normalsaldo im Soll? (Aktiv/Aufwand). */
    public function sollSeite(): bool
    {
        return $this === self::Aktiv || $this === self::Aufwand;
    }
}
