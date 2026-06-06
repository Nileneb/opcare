<?php

namespace App\Domains\Masterdata\Enums;

/**
 * Kategorie eines Bewohner-Dokuments/Fotos. Steuert Aufbewahrungsfrist (medizinisch → § 630f BGB)
 * und Einwilligungspflicht (Foto ohne Behandlungsbezug → § 22 KUG + Art. 9 DSGVO).
 */
enum DokumentKategorie: string
{
    case Wundfoto = 'wundfoto';
    case Befund = 'befund';
    case Vertrag = 'vertrag';
    case Profilfoto = 'profilfoto';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Wundfoto => 'Wundfoto (Behandlungsdoku)',
            self::Befund => 'Befund/Arztbrief',
            self::Vertrag => 'Vertrag/Verwaltung',
            self::Profilfoto => 'Profil-/Eventfoto',
            self::Sonstiges => 'Sonstiges',
        };
    }

    /** Behandlungsbezogen → 10-Jahres-Aufbewahrung (§ 630f Abs. 3 BGB). */
    public function istMedizinisch(): bool
    {
        return $this === self::Wundfoto || $this === self::Befund;
    }

    /** Foto ohne Behandlungsbezug → ausdrückliche Einwilligung nötig (§ 22 KUG / Art. 9 lit. a DSGVO). */
    public function brauchtEinwilligung(): bool
    {
        return $this === self::Profilfoto;
    }

    public function badge(): string
    {
        return match ($this) {
            self::Wundfoto, self::Befund => 'red',
            self::Vertrag => 'gray',
            self::Profilfoto => 'amber',
            self::Sonstiges => 'gray',
        };
    }
}
