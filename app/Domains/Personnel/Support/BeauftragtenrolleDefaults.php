<?php

namespace App\Domains\Personnel\Support;

use App\Domains\Personnel\Models\Beauftragtenrolle;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saat für den Beauftragten-Katalog (Pflicht-/„befähigte-Person"-Rollen) je Einrichtung — quellengestützt
 * recherchiert. Deckt Pflege, Küche, Haustechnik (inkl. Elektrofachkraft + Leiterbeauftragte:r) und Verwaltung ab.
 */
class BeauftragtenrolleDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array{key:string,name:string,basis:string,pflicht:bool,schwelle:?string,bereich:string,auffr:?int}> */
    public static function katalog(): array
    {
        return [
            ['key' => 'hygiene', 'name' => 'Hygienebeauftragte:r in der Pflege', 'basis' => '§ 23/35 IfSG, DGKH', 'pflicht' => true, 'schwelle' => 'alle Einrichtungen', 'bereich' => 'pflege', 'auffr' => 36],
            ['key' => 'brandschutzbeauftragte', 'name' => 'Brandschutzbeauftragte:r', 'basis' => 'vfdb 12-09/01', 'pflicht' => true, 'schwelle' => 'Sonderbau', 'bereich' => 'alle', 'auffr' => 36],
            ['key' => 'brandschutzhelfer', 'name' => 'Brandschutzhelfer:in', 'basis' => 'ASR A2.2', 'pflicht' => true, 'schwelle' => '≥ 5 % der Belegschaft', 'bereich' => 'alle', 'auffr' => 48],
            ['key' => 'sicherheitsbeauftragte', 'name' => 'Sicherheitsbeauftragte:r', 'basis' => '§ 22 SGB VII', 'pflicht' => true, 'schwelle' => 'ab 20 Beschäftigte', 'bereich' => 'alle', 'auffr' => 48],
            ['key' => 'ersthelfer', 'name' => 'Ersthelfer:in', 'basis' => 'DGUV V1 § 26', 'pflicht' => true, 'schwelle' => '10 % der Anwesenden', 'bereich' => 'alle', 'auffr' => 24],
            ['key' => 'datenschutz', 'name' => 'Datenschutzbeauftragte:r', 'basis' => 'Art. 37 DSGVO / § 38 BDSG', 'pflicht' => true, 'schwelle' => 'Gesundheitsdaten (ab Tag 1)', 'bereich' => 'verwaltung', 'auffr' => null],
            ['key' => 'mp_sicherheit', 'name' => 'Beauftr. für Medizinproduktesicherheit', 'basis' => '§ 6 MPBetreibV', 'pflicht' => true, 'schwelle' => 'ab 20 Beschäftigte', 'bereich' => 'pflege', 'auffr' => null],
            ['key' => 'gefahrstoff', 'name' => 'Fachkundige Person Gefahrstoffe', 'basis' => 'GefStoffV', 'pflicht' => true, 'schwelle' => 'bei Gefahrstoffen', 'bereich' => 'hauswirtschaft', 'auffr' => null],
            ['key' => 'qmb', 'name' => 'Qualitätsmanagementbeauftragte:r', 'basis' => '§ 113 SGB XI', 'pflicht' => true, 'schwelle' => 'alle Einrichtungen', 'bereich' => 'verwaltung', 'auffr' => null],
            ['key' => 'praxisanleiter', 'name' => 'Praxisanleiter:in', 'basis' => '§ 4 PflAPrV', 'pflicht' => true, 'schwelle' => 'bei Ausbildung', 'bereich' => 'pflege', 'auffr' => 12],
            ['key' => 'haccp', 'name' => 'HACCP-/Lebensmittelsicherheits-Verantwortliche:r', 'basis' => 'VO (EG) 852/2004', 'pflicht' => true, 'schwelle' => 'Küchenbetrieb', 'bereich' => 'kueche', 'auffr' => null],
            ['key' => 'betriebsarzt', 'name' => 'Betriebsarzt + Fachkraft für Arbeitssicherheit (Sifa)', 'basis' => 'ASiG / DGUV V2', 'pflicht' => true, 'schwelle' => 'ab 1 Beschäftigtem', 'bereich' => 'verwaltung', 'auffr' => null],
            ['key' => 'elektrofachkraft', 'name' => 'Elektrofachkraft (Prüfung elektr. Betriebsmittel)', 'basis' => 'DGUV V3 / TRBS 1203', 'pflicht' => true, 'schwelle' => 'elektr. Anlagen', 'bereich' => 'technik', 'auffr' => null],
            ['key' => 'leiterbeauftragte', 'name' => 'Befähigte Person Leitern & Tritte', 'basis' => 'BetrSichV / DGUV 208-016', 'pflicht' => true, 'schwelle' => 'Leitern vorhanden', 'bereich' => 'technik', 'auffr' => null],
            ['key' => 'aufzugswaerter', 'name' => 'Beauftragte Person für Aufzugsanlagen', 'basis' => 'BetrSichV / TRBS 3121', 'pflicht' => true, 'schwelle' => 'bei Aufzug', 'bereich' => 'technik', 'auffr' => null],
        ];
    }

    /** @return Collection<int, Beauftragtenrolle> */
    public static function ensureFor(int $tenantId): Collection
    {
        foreach (self::katalog() as $r) {
            Beauftragtenrolle::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $r['key']],
                ['name' => $r['name'], 'rechtsbasis' => $r['basis'], 'pflicht' => $r['pflicht'], 'schwelle' => $r['schwelle'], 'bereich' => $r['bereich'], 'auffrischung_monate' => $r['auffr']],
            );
        }

        return Beauftragtenrolle::where('tenant_id', $tenantId)->orderBy('bereich')->orderBy('id')->get();
    }
}
