<?php

namespace App\Domains\Hygiene\Enums;

/**
 * Relevante Erreger der Infektions-Surveillance in der stationären Pflege (§ 23 Abs. 4 IfSG: Aufzeichnung
 * von Erregern mit speziellen Resistenzen und nosokomialen Infektionen). `meldeRelevant()` markiert die nach
 * §§ 6/7 IfSG typischerweise melde- bzw. häufungsmeldepflichtigen Erreger — die konkrete Meldepflicht je Fall
 * (namentlich/Häufung) entscheidet die Fachkraft je Befund.
 */
enum Erreger: string
{
    case Mrsa = 'mrsa';
    case Vre = 'vre';
    case Mrgn3 = 'mrgn3';
    case Mrgn4 = 'mrgn4';
    case CDifficile = 'c_difficile';
    case Norovirus = 'norovirus';
    case Influenza = 'influenza';
    case SarsCov2 = 'sars_cov_2';
    case Skabies = 'skabies';
    case Tuberkulose = 'tuberkulose';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Mrsa => 'MRSA (Methicillin-resistenter S. aureus)',
            self::Vre => 'VRE (Vancomycin-resistente Enterokokken)',
            self::Mrgn3 => '3MRGN (multiresistente gramnegative Erreger)',
            self::Mrgn4 => '4MRGN (multiresistente gramnegative Erreger)',
            self::CDifficile => 'Clostridioides difficile',
            self::Norovirus => 'Norovirus',
            self::Influenza => 'Influenza',
            self::SarsCov2 => 'SARS-CoV-2',
            self::Skabies => 'Skabies (Krätze)',
            self::Tuberkulose => 'Tuberkulose',
            self::Sonstiges => 'sonstiger Erreger',
        };
    }

    /** Multiresistenter Erreger (MRE) im engeren Sinn → KRINKO-Hygienemaßnahmen. */
    public function istMre(): bool
    {
        return in_array($this, [self::Mrsa, self::Vre, self::Mrgn3, self::Mrgn4], true);
    }

    /** Nach §§ 6/7 IfSG melde-/häufungsmeldepflichtig (Default-Vorschlag, je Fall zu prüfen). */
    public function meldeRelevant(): bool
    {
        return in_array($this, [self::Norovirus, self::Influenza, self::SarsCov2, self::Tuberkulose, self::Skabies, self::CDifficile], true);
    }

    public function rechtsbasis(): string
    {
        return $this->meldeRelevant()
            ? '§ 6/§ 7 IfSG (Meldepflicht), § 23 Abs. 4 IfSG (Aufzeichnung)'
            : '§ 23 Abs. 4 IfSG (Aufzeichnung Erreger mit Resistenzen)';
    }
}
