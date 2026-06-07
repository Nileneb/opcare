<?php

namespace App\Domains\Voting\Enums;

// WHY: Naht für späteren GeheimKrypto-Modus (blind-signierter Token, Server-Unverkettbarkeit)
// — Modell-Trennung bleibt unverändert tragfähig (siehe design-spec Abschnitt "Krypto-Härtungspfad").
enum Stimmodus: string
{
    case Geheim = 'geheim';
    case Namentlich = 'namentlich';

    public function label(): string
    {
        return match ($this) {
            self::Geheim => 'Geheim',
            self::Namentlich => 'Namentlich',
        };
    }
}
