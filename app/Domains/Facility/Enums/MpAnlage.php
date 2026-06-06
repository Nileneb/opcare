<?php

namespace App\Domains\Facility\Enums;

/**
 * Anlagen-Zuordnung nach MPBetreibV: bestimmt, ob ein Medizinproduktebuch (§ 13) sowie
 * sicherheitstechnische (STK, § 12) bzw. messtechnische Kontrollen (MTK, § 15) Pflicht sind.
 */
enum MpAnlage: string
{
    case Keine = 'keine';
    case Anlage1 = 'anlage1';
    case Anlage2 = 'anlage2';

    public function label(): string
    {
        return match ($this) {
            self::Keine => 'keine (nur Bestandsverzeichnis § 14)',
            self::Anlage1 => 'Anlage 1 — STK-pflichtig (§ 12)',
            self::Anlage2 => 'Anlage 2 — MTK-pflichtig (§ 15)',
        };
    }

    /** Medizinproduktebuch (§ 13) ist für Anlage-1- und Anlage-2-Produkte zu führen. */
    public function brauchtMedizinproduktebuch(): bool
    {
        return $this !== self::Keine;
    }

    public function brauchtStk(): bool
    {
        return $this === self::Anlage1;
    }

    public function brauchtMtk(): bool
    {
        return $this === self::Anlage2;
    }

    /** Regelintervall der STK für Anlage-1-Produkte (§ 12: spätestens alle 2 Jahre). */
    public function standardStkIntervall(): ?int
    {
        return $this === self::Anlage1 ? 24 : null;
    }
}
