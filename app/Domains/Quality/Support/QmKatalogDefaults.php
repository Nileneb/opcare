<?php

namespace App\Domains\Quality\Support;

use App\Domains\Quality\Enums\QmBereich;
use App\Domains\Quality\Models\QmRequirement;
use Illuminate\Database\Eloquent\Collection;

/**
 * Standard-Katalog der QM-Norm-Checkliste — aus den QPR-Qualitätsbereichen (MD-Bund) und den einrichtungs-
 * weiten Abteilungs-/Querschnittsnormen abgeleitet. Bewusst NICHT abschließend: ein belastbarer Startsatz,
 * je Einrichtung erweiterbar. Jede Anforderung nennt ihre Norm; §-Gesetze verlinken den amtlichen Text.
 *
 * Nebenprodukt (Vision „Ein Altenheim, eine App"): die Bereiche + Anforderungen sind die Landkarte, welche
 * Abteilungen es gibt und welche Daten/Nachweise zu führen sind.
 */
class QmKatalogDefaults
{
    public const VERSION = '1.0.0';

    private const IFSG = 'https://www.gesetze-im-internet.de/ifsg/';

    private const SGB11 = 'https://www.gesetze-im-internet.de/sgb_11/';

    /** @return array<int, array{schluessel:string, bereich:QmBereich, norm:string, anforderung:string, gesetz_url:?string}> */
    public static function rules(): array
    {
        $b = QmBereich::class;

        return [
            // QB 1 — Mobilität & Selbstversorgung (DNQP-Expertenstandards)
            self::r('qb1_sturz', $b::Qb1MobilitaetSelbstversorgung, 'QPR QB1 · DNQP Sturzprophylaxe', 'Sturzrisiko wird systematisch eingeschätzt und prophylaktische Maßnahmen sind geplant und umgesetzt.'),
            self::r('qb1_dekubitus', $b::Qb1MobilitaetSelbstversorgung, 'QPR QB1 · DNQP Dekubitusprophylaxe', 'Dekubitusrisiko (z. B. Braden) wird erhoben; Bewegungsförderung/Druckentlastung ist nachvollziehbar.'),
            self::r('qb1_mobilitaet', $b::Qb1MobilitaetSelbstversorgung, 'QPR QB1 · DNQP Mobilität', 'Erhaltung und Förderung der Mobilität ist geplant und evaluiert.'),

            // QB 2 — Krankheits-/therapiebedingte Anforderungen
            self::r('qb2_schmerz', $b::Qb2KrankheitTherapie, 'QPR QB2 · DNQP Schmerzmanagement', 'Schmerz wird systematisch erfasst (z. B. NRS/BESD) und behandelt.'),
            self::r('qb2_wunde', $b::Qb2KrankheitTherapie, 'QPR QB2 · DNQP chronische Wunden', 'Chronische Wunden werden fachgerecht versorgt und dokumentiert.'),
            self::r('qb2_medikation', $b::Qb2KrankheitTherapie, 'QPR QB2', 'Sichere Arzneimittelversorgung: ärztliche Anordnung, Stellen, Gabe und BtM-Nachweis sind geregelt.'),
            self::r('qb2_kontinenz', $b::Qb2KrankheitTherapie, 'QPR QB2 · DNQP Kontinenzförderung', 'Kontinenzsituation wird eingeschätzt und gefördert.'),

            // QB 3 — Alltagsleben & soziale Kontakte
            self::r('qb3_betreuung', $b::Qb3AlltagSozialeKontakte, '§ 43b SGB XI', 'Zusätzliche Betreuung/Aktivierung durch qualifizierte Betreuungskräfte ist sichergestellt.', self::SGB11.'__43b.html'),
            self::r('qb3_biografie', $b::Qb3AlltagSozialeKontakte, 'QPR QB3', 'Biografie und individuelle Tagesstruktur werden erhoben und berücksichtigt.'),

            // QB 4 — Besondere Bedarfs- & Versorgungssituationen
            self::r('qb4_ernaehrung', $b::Qb4BesondereBedarfe, 'QPR QB4 · DNQP Ernährungsmanagement', 'Mangelernährungs-/Dehydratationsrisiko wird erfasst; Ernährung/Flüssigkeit ist gesichert.'),
            self::r('qb4_demenz', $b::Qb4BesondereBedarfe, 'QPR QB4', 'Umgang mit herausforderndem Verhalten / Demenz ist konzeptionell geregelt.'),
            self::r('qb4_fem', $b::Qb4BesondereBedarfe, 'QPR QB4 · § 1831 BGB', 'Freiheitsentziehende Maßnahmen nur mit richterlicher Genehmigung; Vermeidung wird angestrebt (Werdenfelser Weg).', 'https://www.gesetze-im-internet.de/bgb/__1831.html'),
            self::r('qb4_palliativ', $b::Qb4BesondereBedarfe, 'QPR QB4', 'Palliativ- und Sterbebegleitung ist konzeptionell verankert.'),

            // QB 5 — Bedarfsübergreifende fachliche Anforderungen
            self::r('qb5_ueberleitung', $b::Qb5FachlicheAnforderungen, 'QPR QB5 · DNQP Entlassungsmanagement', 'Überleitung/Entlassung ist organisiert (Überleitungsbogen, Informationsweitergabe).'),
            self::r('qb5_notfall', $b::Qb5FachlicheAnforderungen, 'QPR QB5', 'Notfallmanagement: Abläufe, Erreichbarkeiten und Notfallausstattung sind geregelt.'),
            self::r('qb5_aerztlich', $b::Qb5FachlicheAnforderungen, 'QPR QB5', 'Ärztliche und therapeutische Versorgung ist sichergestellt und koordiniert.'),

            // QB 6 — Einrichtungsorganisation & Qualitätsmanagement
            self::r('qb6_qmsystem', $b::Qb6OrganisationQm, '§ 113 SGB XI · DIN EN 15224', 'Einrichtungsinternes QM-System (QM-Handbuch, Prozesse, interne Audits) ist etabliert.', self::SGB11.'__113.html'),
            self::r('qb6_beschwerde', $b::Qb6OrganisationQm, 'QPR QB6', 'Beschwerdemanagement mit dokumentierter Bearbeitung ist eingerichtet.'),
            self::r('qb6_fortbildung', $b::Qb6OrganisationQm, 'QPR QB6', 'Fortbildungsplanung und Nachweise je Mitarbeiter:in liegen vor.'),
            self::r('qb6_fachkraftquote', $b::Qb6OrganisationQm, 'HeimPersV / Landesrecht', 'Fachkraftquote wird eingehalten und nachgewiesen.'),

            // Hygiene & Infektionsschutz (IfSG / RKI)
            self::r('hyg_plan', $b::HygieneInfektionsschutz, '§ 23 IfSG · RKI', 'Einrichtungsspezifischer Hygieneplan liegt vor und wird umgesetzt.', self::IFSG.'__23.html'),
            self::r('hyg_beauftragte', $b::HygieneInfektionsschutz, '§ 23 IfSG', 'Hygienebeauftragte:r ist benannt und qualifiziert.', self::IFSG.'__23.html'),
            self::r('hyg_masern', $b::HygieneInfektionsschutz, '§ 20 Abs. 9 IfSG', 'Masernschutznachweise des Personals liegen vor (siehe Personalakte).', self::IFSG.'__20.html'),
            self::r('hyg_belehrung', $b::HygieneInfektionsschutz, '§ 43 IfSG', 'Belehrung des Lebensmittelpersonals ist erfolgt und dokumentiert.', self::IFSG.'__43.html'),
            self::r('hyg_meldung', $b::HygieneInfektionsschutz, '§ 6 IfSG', 'Meldepflichtige Infektionen/Ausbrüche werden an das Gesundheitsamt gemeldet.', self::IFSG.'__6.html'),

            // Datenschutz (DSGVO)
            self::r('ds_vvt', $b::Datenschutz, 'Art. 30 DSGVO', 'Verzeichnis von Verarbeitungstätigkeiten wird geführt.', 'https://dsgvo-gesetz.de/art-30-dsgvo/'),
            self::r('ds_dsb', $b::Datenschutz, 'Art. 37 DSGVO', 'Datenschutzbeauftragte:r ist benannt und gemeldet.', 'https://dsgvo-gesetz.de/art-37-dsgvo/'),
            self::r('ds_tom', $b::Datenschutz, 'Art. 32 DSGVO', 'Technische und organisatorische Maßnahmen (TOM) sind dokumentiert.', 'https://dsgvo-gesetz.de/art-32-dsgvo/'),
            self::r('ds_avv', $b::Datenschutz, 'Art. 28 DSGVO', 'Auftragsverarbeitungsverträge mit Dienstleistern liegen vor.', 'https://dsgvo-gesetz.de/art-28-dsgvo/'),

            // Arbeits- & Brandschutz
            self::r('as_gefaehrdung', $b::ArbeitsschutzBrandschutz, '§ 5 ArbSchG', 'Gefährdungsbeurteilung der Arbeitsplätze liegt vor und ist aktuell.', 'https://www.gesetze-im-internet.de/arbschg/__5.html'),
            self::r('as_vorsorge', $b::ArbeitsschutzBrandschutz, 'ArbMedVV', 'Arbeitsmedizinische Vorsorge ist organisiert.'),
            self::r('as_dguv3', $b::ArbeitsschutzBrandschutz, 'DGUV V3', 'Prüfung ortsveränderlicher elektrischer Betriebsmittel ist nachgewiesen.'),
            self::r('as_brandschutz', $b::ArbeitsschutzBrandschutz, 'Brandschutzordnung · DIN 14096', 'Brandschutzordnung, Flucht-/Rettungswege und Räumungsübung sind aktuell.'),
            self::r('as_ersthelfer', $b::ArbeitsschutzBrandschutz, 'DGUV Vorschrift 1', 'Ausreichend ausgebildete Ersthelfer:innen sind vorhanden.'),

            // Hauswirtschaft & Verpflegung
            self::r('hw_haccp', $b::HauswirtschaftVerpflegung, 'LMHV · VO (EG) 852/2004 (HACCP)', 'HACCP-Eigenkontrollkonzept für die Speisenversorgung ist umgesetzt.'),
            self::r('hw_allergene', $b::HauswirtschaftVerpflegung, 'LMIV VO (EU) 1169/2011', 'Allergenkennzeichnung der Speisen; die Küche kennt die Bewohner-Allergien.'),
            self::r('hw_dge', $b::HauswirtschaftVerpflegung, 'DGE-Qualitätsstandard', 'Verpflegung folgt dem DGE-Qualitätsstandard für stationäre Senioreneinrichtungen.'),

            // Haustechnik & Instandhaltung
            self::r('ht_instand', $b::HaustechnikInstandhaltung, 'DIN 31051', 'Instandhaltungs-/Wartungsplan für Gebäude und Anlagen liegt vor.'),
            self::r('ht_legionellen', $b::HaustechnikInstandhaltung, 'TrinkwV', 'Trinkwasser-/Legionellenuntersuchung ist terminiert und dokumentiert.', 'https://www.gesetze-im-internet.de/trinkwv_2023/'),
            self::r('ht_mpbetreib', $b::HaustechnikInstandhaltung, 'MPBetreibV', 'Prüf-/Wartungspflichten für Medizinprodukte (z. B. Pflegebetten, Lifter) sind erfüllt.'),
            self::r('ht_aufzug', $b::HaustechnikInstandhaltung, 'BetrSichV', 'Prüfpflichtige Anlagen (Aufzug, Brandmeldeanlage) sind geprüft.'),

            // Verwaltung & Heimrecht
            self::r('vw_heimvertrag', $b::VerwaltungHeimrecht, 'WBVG', 'Wohn- und Betreuungsverträge erfüllen die WBVG-Vorgaben.', 'https://www.gesetze-im-internet.de/wbvg/'),
            self::r('vw_mitwirkung', $b::VerwaltungHeimrecht, 'Landesheimrecht/WTG · HeimmwV', 'Bewohnervertretung/-beirat ist eingerichtet (Mitwirkung).'),
            self::r('vw_heimaufsicht', $b::VerwaltungHeimrecht, 'Landesheimrecht', 'Anzeige-/Meldepflichten gegenüber der Heimaufsicht werden erfüllt.'),
            self::r('vw_aufbewahrung', $b::VerwaltungHeimrecht, '§ 630f BGB', 'Pflegedokumentation wird mindestens 10 Jahre aufbewahrt.', 'https://www.gesetze-im-internet.de/bgb/__630f.html'),
        ];
    }

    /** @return array{schluessel:string, bereich:QmBereich, norm:string, anforderung:string, gesetz_url:?string} */
    private static function r(string $schluessel, QmBereich $bereich, string $norm, string $anforderung, ?string $url = null): array
    {
        return ['schluessel' => $schluessel, 'bereich' => $bereich, 'norm' => $norm, 'anforderung' => $anforderung, 'gesetz_url' => $url];
    }

    /**
     * Seedet die Standard-Anforderungen idempotent für den Mandanten (eigene/editierte bleiben unangetastet).
     *
     * @return Collection<int, QmRequirement>
     */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::rules() as $rule) {
            QmRequirement::firstOrCreate(
                ['tenant_id' => $tenantId, 'schluessel' => $rule['schluessel']],
                [...$rule, 'tenant_id' => $tenantId],
            );
        }

        return QmRequirement::where('tenant_id', $tenantId)->orderBy('id')->get();
    }
}
