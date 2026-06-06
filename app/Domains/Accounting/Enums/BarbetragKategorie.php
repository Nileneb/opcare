<?php

namespace App\Domains\Accounting\Enums;

/**
 * Verwendungskategorie einer Barbetrags-Auszahlung — Grundlage der Budget-Setzungen (Budget je Kategorie).
 * Bewusst kuratierte, erweiterbare Liste typischer Taschengeld-Verwendungen in der stationären Pflege.
 */
enum BarbetragKategorie: string
{
    case Friseur = 'friseur';
    case Koerperpflege = 'koerperpflege';   // Fußpflege, Kosmetik
    case Kleidung = 'kleidung';
    case Freizeit = 'freizeit';             // Ausflüge, Veranstaltungen, Zeitschriften
    case Bargeld = 'bargeld';               // Barauszahlung an Bewohner/Betreuer
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Friseur => 'Friseur',
            self::Koerperpflege => 'Körperpflege (Fußpflege/Kosmetik)',
            self::Kleidung => 'Kleidung',
            self::Freizeit => 'Freizeit/Teilhabe',
            self::Bargeld => 'Barauszahlung',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
