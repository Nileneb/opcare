<?php

namespace App\Domains\Catering\Enums;

/**
 * Die 14 kennzeichnungspflichtigen Allergene nach LMIV (VO (EU) Nr. 1169/2011, Anhang II). `keywords()`
 * dient dem unscharfen Abgleich gegen den Freitext einer Bewohner-Allergie (`ResidentAllergy.substanz`) —
 * als Hinweis, nicht als Garantie.
 */
enum LmivAllergen: string
{
    case Gluten = 'gluten';
    case Krebstiere = 'krebstiere';
    case Eier = 'eier';
    case Fisch = 'fisch';
    case Erdnuesse = 'erdnuesse';
    case Soja = 'soja';
    case Milch = 'milch';
    case Schalenfruechte = 'schalenfruechte';
    case Sellerie = 'sellerie';
    case Senf = 'senf';
    case Sesam = 'sesam';
    case Sulfite = 'sulfite';
    case Lupinen = 'lupinen';
    case Weichtiere = 'weichtiere';

    public function label(): string
    {
        return match ($this) {
            self::Gluten => 'Glutenhaltiges Getreide',
            self::Krebstiere => 'Krebstiere',
            self::Eier => 'Eier',
            self::Fisch => 'Fisch',
            self::Erdnuesse => 'Erdnüsse',
            self::Soja => 'Soja',
            self::Milch => 'Milch/Laktose',
            self::Schalenfruechte => 'Schalenfrüchte (Nüsse)',
            self::Sellerie => 'Sellerie',
            self::Senf => 'Senf',
            self::Sesam => 'Sesamsamen',
            self::Sulfite => 'Schwefeldioxid/Sulfite',
            self::Lupinen => 'Lupinen',
            self::Weichtiere => 'Weichtiere',
        };
    }

    /** @return array<int, string> kleingeschriebene Suchbegriffe für den Freitext-Abgleich */
    public function keywords(): array
    {
        return match ($this) {
            self::Gluten => ['gluten', 'weizen', 'dinkel', 'gerste', 'roggen', 'hafer'],
            self::Krebstiere => ['krebs', 'krabbe', 'garnele', 'hummer', 'shrimp', 'scampi'],
            self::Eier => ['ei', 'eier', 'eigelb', 'eiweiß'],
            self::Fisch => ['fisch', 'lachs', 'thunfisch', 'hering'],
            self::Erdnuesse => ['erdnuss', 'erdnüsse', 'peanut'],
            self::Soja => ['soja', 'tofu'],
            self::Milch => ['milch', 'laktose', 'lactose', 'molke', 'käse', 'butter', 'sahne'],
            self::Schalenfruechte => ['nuss', 'nüsse', 'mandel', 'haselnuss', 'walnuss', 'cashew', 'pistazie', 'pekan'],
            self::Sellerie => ['sellerie'],
            self::Senf => ['senf'],
            self::Sesam => ['sesam'],
            self::Sulfite => ['sulfit', 'schwefel'],
            self::Lupinen => ['lupine', 'lupinen'],
            self::Weichtiere => ['muschel', 'weichtier', 'tintenfisch', 'auster', 'schnecke'],
        };
    }
}
