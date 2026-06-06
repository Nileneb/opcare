<?php

namespace App\Domains\Quality\Enums;

/**
 * Prüf-/Organisationsbereiche der QM-Norm-Checkliste. QB1–QB6 entsprechen den Qualitätsbereichen der
 * QPR vollstationär (MD-Bund); die übrigen bilden die einrichtungsweiten Abteilungs-/Querschnittsnormen ab.
 * Die Bereiche sind zugleich die „welche Abteilung"-Landkarte aus der Vision.
 */
enum QmBereich: string
{
    case Qb1MobilitaetSelbstversorgung = 'qb1_mobilitaet';
    case Qb2KrankheitTherapie = 'qb2_krankheit_therapie';
    case Qb3AlltagSozialeKontakte = 'qb3_alltag';
    case Qb4BesondereBedarfe = 'qb4_besondere_bedarfe';
    case Qb5FachlicheAnforderungen = 'qb5_fachlich';
    case Qb6OrganisationQm = 'qb6_organisation_qm';
    case HygieneInfektionsschutz = 'hygiene';
    case Datenschutz = 'datenschutz';
    case ArbeitsschutzBrandschutz = 'arbeitsschutz';
    case HauswirtschaftVerpflegung = 'hauswirtschaft';
    case HaustechnikInstandhaltung = 'haustechnik';
    case VerwaltungHeimrecht = 'verwaltung';

    public function label(): string
    {
        return match ($this) {
            self::Qb1MobilitaetSelbstversorgung => 'QB 1 — Mobilität & Selbstversorgung',
            self::Qb2KrankheitTherapie => 'QB 2 — Krankheits-/therapiebedingte Anforderungen',
            self::Qb3AlltagSozialeKontakte => 'QB 3 — Alltagsleben & soziale Kontakte',
            self::Qb4BesondereBedarfe => 'QB 4 — Besondere Bedarfs- & Versorgungssituationen',
            self::Qb5FachlicheAnforderungen => 'QB 5 — Bedarfsübergreifende fachliche Anforderungen',
            self::Qb6OrganisationQm => 'QB 6 — Einrichtungsorganisation & Qualitätsmanagement',
            self::HygieneInfektionsschutz => 'Hygiene & Infektionsschutz',
            self::Datenschutz => 'Datenschutz',
            self::ArbeitsschutzBrandschutz => 'Arbeits- & Brandschutz',
            self::HauswirtschaftVerpflegung => 'Hauswirtschaft & Verpflegung',
            self::HaustechnikInstandhaltung => 'Haustechnik & Instandhaltung',
            self::VerwaltungHeimrecht => 'Verwaltung & Heimrecht',
        };
    }

    /** Prüfebene: Bewohner (QB1–4) vs. Einrichtung (Rest). */
    public function ebene(): string
    {
        return in_array($this, [
            self::Qb1MobilitaetSelbstversorgung, self::Qb2KrankheitTherapie,
            self::Qb3AlltagSozialeKontakte, self::Qb4BesondereBedarfe,
        ], true) ? 'Bewohnerebene' : 'Einrichtungsebene';
    }
}
