<?php

namespace App\Domains\Capture\Enums;

/**
 * Ziel-Slot, in den ein analysierter Beleg einsortiert werden kann. Bewusst klein gehalten: in dieser Iteration
 * ist nur die Buchhaltungs-Buchung als konkreter Schreibpfad umgesetzt; alles andere ist „unklar" und wird der
 * menschlichen Entscheidung überlassen (kein geratenes Ziel).
 */
enum ZielTyp: string
{
    case BuchhaltungBeleg = 'buchhaltung_beleg';
    case Unklar = 'unklar';

    public function label(): string
    {
        return match ($this) {
            self::BuchhaltungBeleg => 'Buchhaltung (Buchung)',
            self::Unklar => 'unklar — manuell zuordnen',
        };
    }

    public function buchbar(): bool
    {
        return $this === self::BuchhaltungBeleg;
    }
}
