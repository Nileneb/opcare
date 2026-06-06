<?php

namespace App\Domains\Personnel\Support;

use App\Domains\Personnel\Models\Kompetenz;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saat für den Kompetenz-Katalog (Skill-Baum) je Einrichtung — Grundberufe, Weiterbildungen und interne
 * Schulungen der Altenpflege mit Voraussetzungen, Gültigkeit und Auffrischung (quellengestützt recherchiert).
 * Erweiterbar; die später folgende IHK-/„geschützte Berufe"-Recherche ergänzt den Katalog.
 */
class KompetenzDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array{key:string,name:string,typ:string,fk:bool,basis:?string,std:?int,gueltig:?int,auffr:?int,vor:array<int,string>}> */
    public static function katalog(): array
    {
        return [
            ['key' => 'pflegefachkraft', 'name' => 'Pflegefachfrau/-mann', 'typ' => 'grundberuf', 'fk' => true, 'basis' => 'PflBG (3 J.)', 'std' => 4600, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'pflegeassistenz', 'name' => 'Pflege(fach)assistenz / Altenpflegehelfer:in', 'typ' => 'grundberuf', 'fk' => false, 'basis' => 'Landesrecht (1–2 J.)', 'std' => null, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'pflegehilfskraft', 'name' => 'Pflegehilfskraft (ungelernt/angelernt)', 'typ' => 'grundberuf', 'fk' => false, 'basis' => null, 'std' => null, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'ersthelfer', 'name' => 'Ersthelfer:in / BLS', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'DGUV V1 § 26', 'std' => 9, 'gueltig' => 24, 'auffr' => 24, 'vor' => []],
            ['key' => 'kinaesthetics', 'name' => 'Kinaesthetics (Grundkurs)', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'Kinaesthetics-Verband', 'std' => 16, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'basale_stimulation', 'name' => 'Basale Stimulation (Grundkurs)', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'Basale Stimulation e.V.', 'std' => 24, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'lg1', 'name' => 'Behandlungspflege LG1', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => '§ 132a SGB V', 'std' => 186, 'gueltig' => null, 'auffr' => 12, 'vor' => ['pflegehilfskraft']],
            ['key' => 'lg2', 'name' => 'Behandlungspflege LG2', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => '§ 132a SGB V', 'std' => 186, 'gueltig' => null, 'auffr' => 12, 'vor' => ['lg1']],
            ['key' => 'sc_injektion', 'name' => 'SC-Injektion / Insulin („Spritzenschein")', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'ärztl. Delegation', 'std' => 8, 'gueltig' => null, 'auffr' => 12, 'vor' => []],
            ['key' => 'peg_port', 'name' => 'PEG-/Portversorgung', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'Einweisung', 'std' => 8, 'gueltig' => null, 'auffr' => 12, 'vor' => ['lg2']],
            ['key' => 'praxisanleiter', 'name' => 'Praxisanleiter:in', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => '§ 4 PflAPrV', 'std' => 300, 'gueltig' => null, 'auffr' => 12, 'vor' => ['pflegefachkraft']],
            ['key' => 'wundexperte_icw', 'name' => 'Wundexperte:in ICW®', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'ICW e.V.', 'std' => 72, 'gueltig' => 60, 'auffr' => 12, 'vor' => ['pflegefachkraft']],
            ['key' => 'pain_nurse', 'name' => 'Pain Nurse / Algesiolog. Fachassistenz', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DGS', 'std' => 130, 'gueltig' => 12, 'auffr' => 12, 'vor' => ['pflegefachkraft']],
            ['key' => 'hygienebeauftragte', 'name' => 'Hygienebeauftragte:r in der Pflege', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => '§ 23/35 IfSG, DGKH', 'std' => 80, 'gueltig' => null, 'auffr' => 36, 'vor' => ['pflegefachkraft']],
            ['key' => 'geronto_fachkraft', 'name' => 'Gerontopsychiatrische Fachkraft', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'Landesrecht', 'std' => 530, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'palliative_care', 'name' => 'Palliative-Care-Fachkraft', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DGP/DHPV 160 h', 'std' => 160, 'gueltig' => null, 'auffr' => 12, 'vor' => ['pflegefachkraft']],
            ['key' => 'tracheostoma', 'name' => 'Tracheostoma-/Beatmungspflege', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'HNO-Curriculum/DIGAB', 'std' => 50, 'gueltig' => null, 'auffr' => 12, 'vor' => ['pflegefachkraft']],
            ['key' => 'wbl', 'name' => 'Wohnbereichsleitung (WBL)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'Landesrecht', 'std' => 300, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'pdl', 'name' => 'Pflegedienstleitung (PDL)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => '§ 71 SGB XI (460 h)', 'std' => 460, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],

            // Erweiterung nach Recherche (DKG-Fachweiterbildungen, Fachgesellschaften, Leitung/IHK). „komplett" im
            // Sinne der gängigen anerkannten Qualifikationen — keine zentrale amtliche Tabelle existiert (siehe Doku).
            ['key' => 'bsc_pflege_heilkundlich', 'name' => 'Pflegestudium B.Sc. (erweiterte heilkundliche Kompetenz)', 'typ' => 'grundberuf', 'fk' => true, 'basis' => 'PflBG / PflStudStG; BEEP ab 2026', 'std' => 7200, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'fwb_gerontopsychiatrie', 'name' => 'Fachweiterbildung Geriatrie/Gerontopsychiatrie', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DKG / Pflegekammer NRW', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'fwb_intensiv_anaesthesie', 'name' => 'Fachweiterbildung Intensivpflege & Anästhesie', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DKG', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'fwb_onkologie', 'name' => 'Fachweiterbildung Onkologische Pflege', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DKG', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'fwb_psychiatrie', 'name' => 'Fachweiterbildung Psychiatrische Pflege', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DKG', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'fwb_hygienefachkraft', 'name' => 'Hygienefachkraft (Fachweiterbildung)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DGKH/RKI, DKG', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'fwb_stationsleitung', 'name' => 'Leitung einer Station/Funktionseinheit', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DKG', 'std' => 720, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'validation_feil', 'name' => 'Validation (nach Naomi Feil)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'VTI/DVG', 'std' => 40, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'diabetes_pflege_ddg', 'name' => 'Diabetes-Pflegefachkraft DDG (Langzeitpflege)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DDG', 'std' => 160, 'gueltig' => null, 'auffr' => 24, 'vor' => ['pflegefachkraft']],
            ['key' => 'diabetes_berater_ddg', 'name' => 'Diabetesberater:in DDG', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DDG', 'std' => 520, 'gueltig' => null, 'auffr' => 24, 'vor' => ['diabetes_pflege_ddg']],
            ['key' => 'ernaehrungsbeauftragte', 'name' => 'Pflegeexpert:in Ernährungsmanagement', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DGEM', 'std' => 100, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'qmb_pflege', 'name' => 'Qualitätsmanagementbeauftragte:r (QMB)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DIN EN ISO 9001', 'std' => 80, 'gueltig' => null, 'auffr' => 48, 'vor' => []],
            ['key' => 'case_manager_dgcc', 'name' => 'Case Manager:in (DGCC)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'DGCC', 'std' => 210, 'gueltig' => null, 'auffr' => 48, 'vor' => ['pflegefachkraft']],
            ['key' => 'sturzbeauftragter', 'name' => 'Sturzbeauftragte:r', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'DNQP-Expertenstandard', 'std' => 24, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'kontinenzbeauftragter', 'name' => 'Kontinenzbeauftragte:r', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'DNQP-Expertenstandard', 'std' => 24, 'gueltig' => null, 'auffr' => null, 'vor' => ['pflegefachkraft']],
            ['key' => 'schmerzbeauftragter', 'name' => 'Schmerzbeauftragte:r (Basis)', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'DNQP-Expertenstandard', 'std' => 24, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'bobath_grundkurs', 'name' => 'Bobath-Pflegegrundkurs (BIKA)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'BIKA/VeBID', 'std' => 80, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'bobath_aufbaukurs', 'name' => 'Bobath-Pflegeaufbaukurs', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'BIKA/VeBID', 'std' => 40, 'gueltig' => null, 'auffr' => 48, 'vor' => ['bobath_grundkurs']],
            ['key' => 'bls_notfalltraining', 'name' => 'Notfalltraining / BLS + AED', 'typ' => 'interne_schulung', 'fk' => false, 'basis' => 'ERC/GRC', 'std' => 8, 'gueltig' => 36, 'auffr' => 12, 'vor' => []],
            ['key' => 'einrichtungsleitung', 'name' => 'Einrichtungsleitung / Heimleitung', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'HeimPersV / Landesrecht', 'std' => 600, 'gueltig' => null, 'auffr' => null, 'vor' => []],
            ['key' => 'fachwirt_gesundheit_ihk', 'name' => 'Geprüfte:r Fachwirt:in im Gesundheits- und Sozialwesen (IHK)', 'typ' => 'weiterbildung', 'fk' => false, 'basis' => 'BBiG / IHK (DQR 6)', 'std' => 600, 'gueltig' => null, 'auffr' => null, 'vor' => []],
        ];
    }

    /** @return Collection<int, Kompetenz> */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::katalog() as $k) {
            Kompetenz::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $k['key']],
                [
                    'name' => $k['name'], 'typ' => $k['typ'], 'ist_fachkraft' => $k['fk'], 'rechtsbasis' => $k['basis'],
                    'umfang_stunden' => $k['std'], 'gueltigkeit_monate' => $k['gueltig'], 'auffrischung_monate' => $k['auffr'],
                ],
            );
        }

        $byKey = Kompetenz::where('tenant_id', $tenantId)->get()->keyBy('key');
        foreach (self::katalog() as $k) {
            if ($k['vor'] === []) {
                continue;
            }
            $ids = collect($k['vor'])->map(fn ($vk) => $byKey[$vk]?->id)->filter()->all();
            $byKey[$k['key']]?->voraussetzungen()->syncWithoutDetaching($ids);
        }

        return $byKey->values();
    }
}
