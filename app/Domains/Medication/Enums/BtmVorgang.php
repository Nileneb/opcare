<?php

namespace App\Domains\Medication\Enums;

/**
 * Vorgangsart einer BtM-Buchung (§ 13 BtMVV). Das Vorzeichen bestimmt die Bestandsänderung; die Vernichtung
 * verlangt zusätzlich das Zwei-Zeugen-Prinzip (BtMG § 16).
 */
enum BtmVorgang: string
{
    case Lieferung = 'lieferung';            // Zugang (+) — von Apotheke, mit BtM-Rezept
    case Gabe = 'gabe';                      // Abgang (−) — Verabreichung an den Bewohner
    case Vernichtung = 'vernichtung';        // Abgang (−) — 3-Personen-Prinzip
    case RuecknahmeApotheke = 'ruecknahme';  // Abgang (−) — Rückgabe an Apotheke
    case Transfer = 'transfer';              // Abgang (−) — Mitgabe bei Verlegung
    case Korrektur = 'korrektur';            // signierte Stornobuchung (Bezug auf Fehlbuchung)

    public function label(): string
    {
        return match ($this) {
            self::Lieferung => 'Zugang (Lieferung)',
            self::Gabe => 'Gabe an Bewohner',
            self::Vernichtung => 'Vernichtung',
            self::RuecknahmeApotheke => 'Rückgabe an Apotheke',
            self::Transfer => 'Mitgabe (Verlegung)',
            self::Korrektur => 'Korrektur',
        };
    }

    /** +1 Zugang, −1 Abgang. Korrektur trägt das Vorzeichen über die (vorzeichenbehaftete) Menge selbst. */
    public function vorzeichen(): int
    {
        return match ($this) {
            self::Lieferung => 1,
            self::Gabe, self::Vernichtung, self::RuecknahmeApotheke, self::Transfer => -1,
            self::Korrektur => 1,
        };
    }

    public function istZugang(): bool
    {
        return $this === self::Lieferung;
    }

    /** Vernichtung muss von zwei Zeugen bestätigt werden (BtMG § 16). */
    public function brauchtZeugen(): bool
    {
        return $this === self::Vernichtung;
    }
}
