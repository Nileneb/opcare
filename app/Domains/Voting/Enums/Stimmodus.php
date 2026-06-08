<?php

namespace App\Domains\Voting\Enums;

enum Stimmodus: string
{
    case Geheim = 'geheim';
    case Namentlich = 'namentlich';

    // WHY: Stillgelegter Modus (blind-signierter Token, Server-/Root-Unverkettbarkeit). Gebaut als
    // echter Schalter, gesperrt bis zur Krypto-Härtung (config voting.krypto_unverkettbarkeit_aktiv,
    // docs/INBETRIEBNAHME.md §6) — die Krypto selbst ist NICHT implementiert und wird nicht vorgetäuscht.
    case GeheimKrypto = 'geheim_krypto';

    public function label(): string
    {
        return match ($this) {
            self::Geheim => 'Geheim',
            self::Namentlich => 'Namentlich',
            self::GeheimKrypto => 'Geheim (krypto-unverkettbar)',
        };
    }

    /** Anonyme Abgabe (kein Personenbezug an der Stimme) — Geheim und GeheimKrypto. */
    public function istGeheim(): bool
    {
        return $this === self::Geheim || $this === self::GeheimKrypto;
    }

    /** Krypto-gehärteter Modus — stillgelegt bis zur Inbetriebnahme (config-Schalter). */
    public function istKrypto(): bool
    {
        return $this === self::GeheimKrypto;
    }
}
