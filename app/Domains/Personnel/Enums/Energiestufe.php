<?php

namespace App\Domains\Personnel\Enums;

/**
 * Freiwillige Selbsteinschätzung des aktuellen Energie-/Belastungsniveaus (Team-Barometer). Bewusst grob
 * dreistufig — kein Diagnose-Instrument, sondern ein Stimmungsbild für die Leitung (Frühwarnung Überlastung).
 */
enum Energiestufe: int
{
    case Erschoepft = 1;
    case Mittel = 2;
    case Energiegeladen = 3;

    public function label(): string
    {
        return match ($this) {
            self::Erschoepft => 'Erschöpft',
            self::Mittel => 'Geht so',
            self::Energiegeladen => 'Energiegeladen',
        };
    }

    public function ampel(): string
    {
        return match ($this) {
            self::Erschoepft => 'red',
            self::Mittel => 'amber',
            self::Energiegeladen => 'green',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Erschoepft => '🔴',
            self::Mittel => '🟡',
            self::Energiegeladen => '🟢',
        };
    }
}
