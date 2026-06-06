<?php

namespace App\Domains\Personnel\Enums;

/** Tätigkeits-/Qualifikationsgruppe (für Fachkraftquote + Einsatzplanung relevant). */
enum Qualifikation: string
{
    case Pflegefachkraft = 'pflegefachkraft';
    case Pflegehilfskraft = 'pflegehilfskraft';
    case Betreuungskraft = 'betreuungskraft';
    case Auszubildende = 'auszubildende';
    case Leitung = 'leitung';
    case Hauswirtschaft = 'hauswirtschaft';
    case Verwaltung = 'verwaltung';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Pflegefachkraft => 'examinierte Pflegefachkraft',
            self::Pflegehilfskraft => 'Pflegehilfskraft',
            self::Betreuungskraft => 'Betreuungskraft (§ 43b SGB XI)',
            self::Auszubildende => 'Auszubildende:r',
            self::Leitung => 'Leitung (PDL/Heimleitung)',
            self::Hauswirtschaft => 'Hauswirtschaft',
            self::Verwaltung => 'Verwaltung',
            self::Sonstiges => 'Sonstiges',
        };
    }

    /** zählt zur Fachkraftquote (§ 5 HeimPersV / Landesrecht). */
    public function istFachkraft(): bool
    {
        return in_array($this, [self::Pflegefachkraft, self::Leitung], true);
    }
}
