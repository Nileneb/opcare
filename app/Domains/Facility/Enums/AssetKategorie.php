<?php

namespace App\Domains\Facility\Enums;

/** Kategorie eines instand zu haltenden Betriebsmittels (DIN 31051 / einschlägige Prüfnormen). */
enum AssetKategorie: string
{
    case Gebaeude = 'gebaeude';
    case Elektro = 'elektro';
    case Medizinprodukt = 'medizinprodukt';
    case Aufzug = 'aufzug';
    case Brandschutz = 'brandschutz';
    case Trinkwasser = 'trinkwasser';
    case Kuechentechnik = 'kuechentechnik';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Gebaeude => 'Gebäude/Bausubstanz',
            self::Elektro => 'Elektrische Betriebsmittel (DGUV V3)',
            self::Medizinprodukt => 'Medizinprodukt (MPBetreibV)',
            self::Aufzug => 'Aufzugsanlage (BetrSichV)',
            self::Brandschutz => 'Brandschutz/Brandmeldeanlage',
            self::Trinkwasser => 'Trinkwasseranlage (TrinkwV)',
            self::Kuechentechnik => 'Küchentechnik',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
