<?php

namespace App\Domains\Personnel\Enums;

/** Masernschutznachweis nach § 20 Abs. 9 IfSG — Pflicht für Personal in Gesundheits-/Pflegeeinrichtungen. */
enum Masernschutz: string
{
    case Geimpft = 'geimpft';
    case ImmunSerologisch = 'immun_serologisch';
    case Kontraindiziert = 'kontraindiziert';
    case Offen = 'offen';

    public function label(): string
    {
        return match ($this) {
            self::Geimpft => 'Impfschutz nachgewiesen (2 Impfungen)',
            self::ImmunSerologisch => 'Immunität serologisch belegt',
            self::Kontraindiziert => 'ärztliche Kontraindikation',
            self::Offen => 'offen / nicht nachgewiesen',
        };
    }

    public function erfuellt(): bool
    {
        return $this !== self::Offen;
    }
}
