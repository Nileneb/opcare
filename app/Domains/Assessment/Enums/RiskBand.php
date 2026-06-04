<?php

namespace App\Domains\Assessment\Enums;

enum RiskBand: string
{
    case Kein = 'kein';
    case Gering = 'gering';
    case Mittel = 'mittel';
    case Hoch = 'hoch';
    case SehrHoch = 'sehr_hoch';

    public function label(): string
    {
        return match ($this) {
            self::Kein => 'Kein Risiko',
            self::Gering => 'Geringes Risiko',
            self::Mittel => 'Mittleres Risiko',
            self::Hoch => 'Hohes Risiko',
            self::SehrHoch => 'Sehr hohes Risiko',
        };
    }

    public function istKritisch(): bool
    {
        return in_array($this, [self::Hoch, self::SehrHoch], true);
    }
}
