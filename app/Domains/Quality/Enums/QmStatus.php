<?php

namespace App\Domains\Quality\Enums;

/** Bearbeitungsstand einer QM-Anforderung. */
enum QmStatus: string
{
    case Offen = 'offen';
    case InArbeit = 'in_arbeit';
    case Erfuellt = 'erfuellt';
    case NichtZutreffend = 'nicht_zutreffend';

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'offen',
            self::InArbeit => 'in Arbeit',
            self::Erfuellt => 'erfüllt',
            self::NichtZutreffend => 'nicht zutreffend',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Offen => 'red',
            self::InArbeit => 'amber',
            self::Erfuellt => 'green',
            self::NichtZutreffend => 'gray',
        };
    }

    /** Zählt in den Erfüllungsgrad als „erledigt" (erfüllt oder bewusst nicht zutreffend). */
    public function erledigt(): bool
    {
        return $this === self::Erfuellt || $this === self::NichtZutreffend;
    }
}
