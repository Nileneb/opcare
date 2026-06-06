<?php

namespace App\Domains\Quality\Enums;

/**
 * Rechtsgrundlage/Status einer FEM (§ 1831 BGB). Die Einwilligung des Betreuers ersetzt die richterliche
 * Genehmigung NICHT — nicht einwilligungsfähige Bewohner brauchen einen Beschluss des Betreuungsgerichts.
 */
enum FemEinwilligung: string
{
    case BewohnerEingewilligt = 'bewohner_eingewilligt';   // einwilligungsfähig + eingewilligt → keine Genehmigung nötig
    case GenehmigungBeantragt = 'beantragt';               // Antrag beim Betreuungsgericht läuft
    case GenehmigungErteilt = 'genehmigt';                 // Beschluss liegt vor (befristet)
    case NotfallNachzuholen = 'notfall';                   // Gefahr im Verzug → Genehmigung unverzüglich nachholen
    case OhneGenehmigung = 'ohne_genehmigung';             // unzulässig → Eskalation

    public function label(): string
    {
        return match ($this) {
            self::BewohnerEingewilligt => 'Bewohner eingewilligt (einwilligungsfähig)',
            self::GenehmigungBeantragt => 'Genehmigung beantragt',
            self::GenehmigungErteilt => 'Gerichtlich genehmigt',
            self::NotfallNachzuholen => 'Notfall — Genehmigung nachzuholen',
            self::OhneGenehmigung => 'ohne Genehmigung (unzulässig)',
        };
    }
}
