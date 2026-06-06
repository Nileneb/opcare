<?php

namespace App\Domains\Compliance\Enums;

/**
 * Rechtsgrundlage einer Verarbeitung nach Art. 6 DSGVO (allgemein) bzw. Art. 9 Abs. 2 lit. h DSGVO i. V. m.
 * § 22 BDSG (besondere Kategorien — in der Pflege fast immer einschlägig: Gesundheitsdaten).
 */
enum Rechtsgrundlage: string
{
    case Einwilligung = 'einwilligung';
    case Vertrag = 'vertrag';
    case RechtlichePflicht = 'rechtliche_pflicht';
    case LebenswichtigeInteressen = 'lebenswichtige_interessen';
    case OeffentlichesInteresse = 'oeffentliches_interesse';
    case BerechtigtesInteresse = 'berechtigtes_interesse';
    case Gesundheitsdaten = 'gesundheitsdaten';

    public function label(): string
    {
        return match ($this) {
            self::Einwilligung => 'Einwilligung',
            self::Vertrag => 'Vertragserfüllung',
            self::RechtlichePflicht => 'Rechtliche Verpflichtung',
            self::LebenswichtigeInteressen => 'Lebenswichtige Interessen',
            self::OeffentlichesInteresse => 'Öffentliches Interesse',
            self::BerechtigtesInteresse => 'Berechtigtes Interesse',
            self::Gesundheitsdaten => 'Gesundheitsdaten (besondere Kategorie)',
        };
    }

    public function artikel(): string
    {
        return match ($this) {
            self::Einwilligung => 'Art. 6 Abs. 1 lit. a DSGVO',
            self::Vertrag => 'Art. 6 Abs. 1 lit. b DSGVO',
            self::RechtlichePflicht => 'Art. 6 Abs. 1 lit. c DSGVO',
            self::LebenswichtigeInteressen => 'Art. 6 Abs. 1 lit. d DSGVO',
            self::OeffentlichesInteresse => 'Art. 6 Abs. 1 lit. e DSGVO',
            self::BerechtigtesInteresse => 'Art. 6 Abs. 1 lit. f DSGVO',
            self::Gesundheitsdaten => 'Art. 9 Abs. 2 lit. h DSGVO i. V. m. § 22 BDSG',
        };
    }

    /** Besondere Kategorie personenbezogener Daten (Art. 9) → erhöhte Schutzpflicht. */
    public function besondereKategorie(): bool
    {
        return $this === self::Gesundheitsdaten;
    }
}
