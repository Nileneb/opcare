<?php

namespace App\Domains\Identity\Enums;

/**
 * Die 16 deutschen Länder. Seit der Föderalismusreform 2006 ist das Heimrecht Landesrecht — jedes Land hat ein
 * eigenes Heimgesetz (Wohn-/Teilhabe-/Betreuungsqualität), das Nachtdienst, Fachkraftquote, Heimmitwirkung und
 * Meldepflichten regelt. Das Bundesland ist Tenant-Stammdatum (aus der Einrichtungs-Adresse ableitbar), das
 * Heimgesetz je Land ist hier als verifizierbare Norm-Quelle hinterlegt (Kurztitel + Langtitel + amtlicher Link).
 */
enum Bundesland: string
{
    case BW = 'BW';
    case BY = 'BY';
    case BE = 'BE';
    case BB = 'BB';
    case HB = 'HB';
    case HH = 'HH';
    case HE = 'HE';
    case MV = 'MV';
    case NI = 'NI';
    case NW = 'NW';
    case RP = 'RP';
    case SL = 'SL';
    case SN = 'SN';
    case ST = 'ST';
    case SH = 'SH';
    case TH = 'TH';

    public function label(): string
    {
        return match ($this) {
            self::BW => 'Baden-Württemberg',
            self::BY => 'Bayern',
            self::BE => 'Berlin',
            self::BB => 'Brandenburg',
            self::HB => 'Bremen',
            self::HH => 'Hamburg',
            self::HE => 'Hessen',
            self::MV => 'Mecklenburg-Vorpommern',
            self::NI => 'Niedersachsen',
            self::NW => 'Nordrhein-Westfalen',
            self::RP => 'Rheinland-Pfalz',
            self::SL => 'Saarland',
            self::SN => 'Sachsen',
            self::ST => 'Sachsen-Anhalt',
            self::SH => 'Schleswig-Holstein',
            self::TH => 'Thüringen',
        };
    }

    /** Kurztitel des Landesheimgesetzes. */
    public function heimgesetz(): string
    {
        return match ($this) {
            self::BW => 'WTPG',
            self::BY => 'PfleWoqG',
            self::BE => 'WTG Berlin',
            self::BB => 'BbgPBWoG',
            self::HB => 'BremWoBeG',
            self::HH => 'HmbWBG',
            self::HE => 'HGBP',
            self::MV => 'EQG M-V',
            self::NI => 'NuWG',
            self::NW => 'WTG NRW',
            self::RP => 'LWTG',
            self::SL => 'LHeimGS',
            self::SN => 'SächsBeWoG',
            self::ST => 'WTG LSA',
            self::SH => 'SbStG',
            self::TH => 'ThürWTG',
        };
    }

    public function gesetzTitel(): string
    {
        return match ($this) {
            self::BW => 'Wohn-, Teilhabe- und Pflegegesetz',
            self::BY => 'Pflege- und Wohnqualitätsgesetz',
            self::BE => 'Wohnteilhabegesetz',
            self::BB => 'Brandenburgisches Pflege- und Betreuungswohngesetz',
            self::HB => 'Bremisches Wohn- und Betreuungsgesetz',
            self::HH => 'Hamburgisches Wohn- und Betreuungsqualitätsgesetz',
            self::HE => 'Hessisches Gesetz über Betreuungs- und Pflegeleistungen',
            self::MV => 'Einrichtungenqualitätsgesetz',
            self::NI => 'Niedersächsisches Gesetz über unterstützende Wohnformen',
            self::NW => 'Wohn- und Teilhabegesetz',
            self::RP => 'Landesgesetz über Wohnformen und Teilhabe',
            self::SL => 'Landesheimgesetz Saarland',
            self::SN => 'Sächsisches Betreuungs- und Wohnqualitätsgesetz',
            self::ST => 'Wohn- und Teilhabegesetz Sachsen-Anhalt',
            self::SH => 'Selbstbestimmungsstärkungsgesetz',
            self::TH => 'Thüringer Wohn- und Teilhabegesetz',
        };
    }

    /** Amtlicher Volltext des Landesheimgesetzes (Landesrecht-Portal). */
    public function gesetzUrl(): string
    {
        return match ($this) {
            self::BW => 'https://www.landesrecht-bw.de/perma?d=jlr-WoTPGBWrahmen',
            self::BY => 'https://www.gesetze-bayern.de/Content/Document/BayPfleWoqG',
            self::BE => 'https://gesetze.berlin.de/perma?d=jlr-WoBeTeilhGBErahmen',
            self::BB => 'https://bravors.brandenburg.de/gesetze/bbgpbwog',
            self::HB => 'https://www.transparenz.bremen.de/metainformationen/bremisches-wohn-und-betreuungsgesetz-117158',
            self::HH => 'https://www.landesrecht-hamburg.de/perma?d=jlr-WoBetrGHArahmen',
            self::HE => 'https://www.rv.hessenrecht.hessen.de/perma?d=jlr-BetrPflGHErahmen',
            self::MV => 'https://www.landesrecht-mv.de/perma?d=jlr-EinrQualGMVrahmen',
            self::NI => 'https://www.nds-voris.de/jportal/?quelle=jlink&query=NuWoG+ND&psml=bsvorisprod.psml',
            self::NW => 'https://recht.nrw.de/lmi/owa/br_text_anzeigen?v_id=4220071121102217063',
            self::RP => 'https://landesrecht.rlp.de/perma?d=jlr-WoFormTeilhGRPrahmen',
            self::SL => 'https://recht.saarland.de/bssl/document/jlr-HeimGSL2009rahmen',
            self::SN => 'https://www.revosax.sachsen.de/vorschrift/17287-SaechsBeWoG',
            self::ST => 'https://www.landesrecht.sachsen-anhalt.de/perma?d=jlr-WoTeilhGSTrahmen',
            self::SH => 'https://www.gesetze-rechtsprechung.sh.juris.de/perma?d=jlr-SbStGSHrahmen',
            self::TH => 'https://landesrecht.thueringen.de/perma?d=jlr-WoTeilhGTHrahmen',
        };
    }
}
