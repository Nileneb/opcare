<?php

namespace App\Domains\Accounting\Enums;

/**
 * Vorgangsart einer Treuhand-/Barbetragsbuchung (§ 27b SGB XII). Das Vorzeichen bestimmt die Saldoänderung;
 * eine Korrektur trägt ihr Vorzeichen über den (vorzeichenbehafteten) Betrag selbst.
 */
enum TreuhandVorgang: string
{
    case Einzahlung = 'einzahlung';   // Zugang (+) — Angehörige/Rente/Barbetrag
    case Auszahlung = 'auszahlung';   // Abgang (−) — treuhänderische Auszahlung (Friseur etc.)
    case Korrektur = 'korrektur';     // signierte Stornobuchung (Bezug auf Fehlbuchung)

    public function label(): string
    {
        return match ($this) {
            self::Einzahlung => 'Einzahlung',
            self::Auszahlung => 'Auszahlung',
            self::Korrektur => 'Korrektur',
        };
    }

    /** +1 Zugang, −1 Abgang. Korrektur trägt das Vorzeichen über den Betrag selbst. */
    public function vorzeichen(): int
    {
        return match ($this) {
            self::Einzahlung => 1,
            self::Auszahlung => -1,
            self::Korrektur => 1,
        };
    }

    public function istAbgang(): bool
    {
        return $this === self::Auszahlung;
    }
}
