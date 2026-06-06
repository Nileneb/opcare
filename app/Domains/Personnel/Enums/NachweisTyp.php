<?php

namespace App\Domains\Personnel\Enums;

/**
 * Art eines wiederkehrenden Arbeitsschutz-Nachweises je Mitarbeiter:in. Standard-Intervall in Monaten
 * (null = anlassbezogen/einmalig) + Rechtsbezug. Werte sind je Nachweis überschreibbar (Tarif/Anlass).
 */
enum NachweisTyp: string
{
    case Unterweisung = 'unterweisung';
    case Vorsorge = 'vorsorge';
    case ErsteHilfe = 'erste_hilfe';
    case Brandschutzhelfer = 'brandschutzhelfer';
    case Bem = 'bem';

    public function label(): string
    {
        return match ($this) {
            self::Unterweisung => 'Unterweisung (Arbeitsschutz)',
            self::Vorsorge => 'Arbeitsmedizinische Vorsorge',
            self::ErsteHilfe => 'Erste-Hilfe-Ausbildung',
            self::Brandschutzhelfer => 'Brandschutzhelfer:in',
            self::Bem => 'BEM-Gespräch',
        };
    }

    /** Standard-Wiederholungsintervall in Monaten; null = anlassbezogen (kein Fälligkeitsdatum). */
    public function intervallMonate(): ?int
    {
        return match ($this) {
            self::Unterweisung => 12,
            self::Vorsorge => 24,
            self::ErsteHilfe => 24,
            self::Brandschutzhelfer => 60,
            self::Bem => null,
        };
    }

    public function gesetz(): string
    {
        return match ($this) {
            self::Unterweisung => '§ 12 ArbSchG / DGUV V1 § 4',
            self::Vorsorge => 'ArbMedVV',
            self::ErsteHilfe => 'DGUV V1 § 26',
            self::Brandschutzhelfer => 'ASR A2.2',
            self::Bem => '§ 167 Abs. 2 SGB IX',
        };
    }
}
