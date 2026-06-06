<?php

namespace App\Domains\Compliance\Support;

use App\Domains\Compliance\Enums\Rechtsgrundlage;
use App\Domains\Compliance\Models\Verarbeitungstaetigkeit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Standard-Verzeichnis der Verarbeitungstätigkeiten (Art. 30 DSGVO) für eine stationäre Pflegeeinrichtung —
 * die typischen Verarbeitungen mit Zweck, Rechtsgrundlage, Daten-/Betroffenenkategorien, Empfängern und
 * Löschfristen. Bewusst NICHT abschließend: ein belastbarer Startsatz, je Einrichtung editier-/erweiterbar.
 */
class VvtDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array{schluessel:string,name:string,zweck:string,rg:Rechtsgrundlage,betroffene:string,daten:string,empfaenger:?string,loeschfrist:string,tom:string}> */
    public static function katalog(): array
    {
        $g = Rechtsgrundlage::Gesundheitsdaten;
        $v = Rechtsgrundlage::Vertrag;
        $p = Rechtsgrundlage::RechtlichePflicht;

        return [
            [
                'schluessel' => 'pflegedokumentation',
                'name' => 'Pflege- und Betreuungsdokumentation',
                'zweck' => 'Planung, Durchführung und Nachweis der pflegerischen Versorgung (SIS, Maßnahmen, Vitalwerte, Medikation).',
                'rg' => $g, 'betroffene' => 'Bewohner:innen', 'daten' => 'Gesundheits-, Pflege-, Stamm- und Kontaktdaten',
                'empfaenger' => 'behandelnde Ärzt:innen, MD/Pflegekasse (auf Anforderung)',
                'loeschfrist' => '10 Jahre nach Ende der Behandlung (§ 630f BGB)',
                'tom' => 'Rollen-/Mandanten-Trennung, MFA-Pflicht, Feldverschlüsselung, Aktivitätsprotokoll (siehe Sicherheitskonzept).',
            ],
            [
                'schluessel' => 'medikation',
                'name' => 'Arzneimittel- und BtM-Versorgung',
                'zweck' => 'Ärztliche Anordnung, Stellen und Gabe von Arzneimitteln inkl. Betäubungsmittel-Nachweis.',
                'rg' => $g, 'betroffene' => 'Bewohner:innen', 'daten' => 'Diagnosen, Verordnungen, Gabe-/BtM-Nachweise',
                'empfaenger' => 'verordnende Ärzt:innen, Apotheke', 'loeschfrist' => '10 Jahre (§ 630f BGB) / 3 Jahre BtM (§ 13 BtMVV)',
                'tom' => 'Befugnis-Gating der Medikamentengabe, append-only BtM-Journal.',
            ],
            [
                'schluessel' => 'abrechnung',
                'name' => 'Leistungsabrechnung (Pflegekasse/Selbstzahler)',
                'zweck' => 'Abrechnung der Pflege-/Betreuungsleistungen gegenüber Kostenträgern und Bewohner:innen.',
                'rg' => $v, 'betroffene' => 'Bewohner:innen, Kostenträger', 'daten' => 'Stamm-, Vertrags-, Leistungs- und Abrechnungsdaten',
                'empfaenger' => 'Pflege-/Krankenkasse, Sozialhilfeträger, Steuerberatung',
                'loeschfrist' => '10 Jahre (§ 147 AO / § 257 HGB)',
                'tom' => 'getrennte Finanz-Rolle, Zugriffsbeschränkung Buchhaltung.',
            ],
            [
                'schluessel' => 'personalverwaltung',
                'name' => 'Personalverwaltung und Lohnabrechnung',
                'zweck' => 'Begründung/Durchführung/Beendigung des Beschäftigungsverhältnisses inkl. Entgeltabrechnung.',
                'rg' => $v, 'betroffene' => 'Beschäftigte, Bewerber:innen', 'daten' => 'Stamm-, Vertrags-, Qualifikations-, Gesundheits- (Eignung) und Abrechnungsdaten',
                'empfaenger' => 'Finanzamt, Sozialversicherung, Berufsgenossenschaft',
                'loeschfrist' => '10 Jahre Lohnunterlagen; Bewerber:innen 6 Monate nach Absage',
                'tom' => 'verschlüsselte Personalakte (§ 26 BDSG), Zugriff nur Leitung/Personal.',
            ],
            [
                'schluessel' => 'belegung_aufnahme',
                'name' => 'Aufnahme- und Belegungsmanagement',
                'zweck' => 'Heimvertrag, Aufnahme, Verlegung, Entlassung und Bewohnervertretung (Betreuung/Vollmacht).',
                'rg' => $v, 'betroffene' => 'Bewohner:innen, Angehörige, gesetzliche Vertretungen',
                'daten' => 'Stamm-, Vertrags-, Kontakt- und Vertretungsdaten',
                'empfaenger' => 'Betreuungsgericht (auf Anforderung)',
                'loeschfrist' => '10 Jahre nach Vertragsende',
                'tom' => 'Aufgabenkreis-Gating des Vertreter-Portals, Tenant-Scope.',
            ],
            [
                'schluessel' => 'hygiene_infektion',
                'name' => 'Hygiene- und Infektionsschutz-Surveillance',
                'zweck' => 'Aufzeichnung nosokomialer Infektionen und resistenter Erreger sowie Meldungen (§ 23/§ 6 IfSG).',
                'rg' => $p, 'betroffene' => 'Bewohner:innen', 'daten' => 'Erreger-/Resistenz-, Befund- und Maßnahmendaten',
                'empfaenger' => 'Gesundheitsamt (meldepflichtige Fälle)',
                'loeschfrist' => '10 Jahre (Surveillance-Nachweis)',
                'tom' => 'Zugriff nur Pflegefachkräfte/Leitung, Mandanten-Trennung.',
            ],
            [
                'schluessel' => 'videoueberwachung',
                'name' => 'Videoüberwachung der Zugänge (optional)',
                'zweck' => 'Schutz von Eigentum und Personen im Eingangs-/Außenbereich.',
                'rg' => Rechtsgrundlage::BerechtigtesInteresse, 'betroffene' => 'Bewohner:innen, Beschäftigte, Besucher:innen',
                'daten' => 'Bildaufnahmen', 'empfaenger' => 'Strafverfolgungsbehörden (im Anlassfall)',
                'loeschfrist' => '72 Stunden, sofern kein Anlass',
                'tom' => 'verschlüsselter Speicher, Zugriff nur Leitung, Hinweisbeschilderung.',
            ],
        ];
    }

    /** @return Collection<int, Verarbeitungstaetigkeit> */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::katalog() as $e) {
            Verarbeitungstaetigkeit::firstOrCreate(
                ['tenant_id' => $tenantId, 'schluessel' => $e['schluessel']],
                [
                    'name' => $e['name'], 'zweck' => $e['zweck'], 'rechtsgrundlage' => $e['rg'],
                    'kategorien_betroffene' => $e['betroffene'], 'kategorien_daten' => $e['daten'],
                    'empfaenger' => $e['empfaenger'], 'loeschfrist' => $e['loeschfrist'], 'tom' => $e['tom'],
                ],
            );
        }

        return Verarbeitungstaetigkeit::where('tenant_id', $tenantId)->orderBy('id')->get();
    }
}
