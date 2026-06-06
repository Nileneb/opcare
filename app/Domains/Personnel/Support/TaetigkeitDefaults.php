<?php

namespace App\Domains\Personnel\Support;

use App\Domains\Personnel\Models\Kompetenz;
use App\Domains\Personnel\Models\Taetigkeit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saat für die Berechtigungsmatrix (Tätigkeit → Mindestqualifikation/Kompetenz/Delegation). Werte aus der
 * Rechts-Recherche (§ 4 PflBG Vorbehalt, § 132a SGB V LG1/LG2, Delegation ärztlicher Tätigkeiten). Erweiterbar.
 */
class TaetigkeitDefaults
{
    public const VERSION = '1.0.0';

    /** @return array<int, array{key:string,label:string,bereich:string,fk:bool,vorbehalt:bool,kompetenz:?string,arzt:bool}> */
    public static function katalog(): array
    {
        return [
            ['key' => 'sis_abzeichnen', 'label' => 'SIS / Pflegeplanung abzeichnen', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => true, 'kompetenz' => null, 'arzt' => false],
            ['key' => 'assessment_abzeichnen', 'label' => 'Assessment/Risikoeinschätzung abzeichnen', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => true, 'kompetenz' => null, 'arzt' => false],
            ['key' => 'evaluation', 'label' => 'Pflegeprozess-Evaluation', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => true, 'kompetenz' => null, 'arzt' => false],
            ['key' => 'orale_medikation', 'label' => 'Orale Medikamentengabe', 'bereich' => 'medikation', 'fk' => false, 'vorbehalt' => false, 'kompetenz' => 'lg1', 'arzt' => true],
            ['key' => 'blutzucker', 'label' => 'Blutzuckermessung', 'bereich' => 'medikation', 'fk' => false, 'vorbehalt' => false, 'kompetenz' => 'lg1', 'arzt' => false],
            ['key' => 'sc_injektion', 'label' => 'SC-Injektion / Insulin', 'bereich' => 'medikation', 'fk' => false, 'vorbehalt' => false, 'kompetenz' => 'sc_injektion', 'arzt' => true],
            ['key' => 'im_injektion', 'label' => 'IM-Injektion', 'bereich' => 'medikation', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => true],
            ['key' => 'iv_injektion', 'label' => 'IV-Injektion / Infusion', 'bereich' => 'medikation', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => true],
            ['key' => 'blutentnahme', 'label' => 'Venöse Blutentnahme', 'bereich' => 'medikation', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => true],
            ['key' => 'wunde_einfach', 'label' => 'Einfache Wundversorgung', 'bereich' => 'pflege', 'fk' => false, 'vorbehalt' => false, 'kompetenz' => 'lg2', 'arzt' => true],
            ['key' => 'wunde_komplex', 'label' => 'Komplexe/chronische Wundversorgung', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => 'wundexperte_icw', 'arzt' => true],
            ['key' => 'katheter', 'label' => 'Blasenkatheter legen/wechseln', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => true],
            ['key' => 'peg', 'label' => 'PEG-/Portversorgung', 'bereich' => 'pflege', 'fk' => false, 'vorbehalt' => false, 'kompetenz' => 'peg_port', 'arzt' => true],
            ['key' => 'btm_abzeichnen', 'label' => 'BtM verabreichen + abzeichnen', 'bereich' => 'medikation', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => false],
            ['key' => 'fem_anordnen', 'label' => 'FEM anordnen', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => null, 'arzt' => false],
            ['key' => 'praxisanleitung', 'label' => 'Auszubildende anleiten', 'bereich' => 'pflege', 'fk' => true, 'vorbehalt' => false, 'kompetenz' => 'praxisanleiter', 'arzt' => false],
        ];
    }

    /** @return Collection<int, Taetigkeit> */
    public static function ensureFor(int $tenantId): Collection
    {
        KompetenzDefaults::ensureFor($tenantId);
        $kompetenzByKey = Kompetenz::where('tenant_id', $tenantId)->get()->keyBy('key');

        foreach (self::katalog() as $t) {
            Taetigkeit::firstOrCreate(
                ['tenant_id' => $tenantId, 'key' => $t['key']],
                [
                    'label' => $t['label'], 'bereich' => $t['bereich'], 'nur_fachkraft' => $t['fk'], 'vorbehaltsaufgabe' => $t['vorbehalt'],
                    'erforderliche_kompetenz_id' => $t['kompetenz'] ? $kompetenzByKey[$t['kompetenz']]?->id : null,
                    'arzt_anordnung_noetig' => $t['arzt'],
                ],
            );
        }

        return Taetigkeit::where('tenant_id', $tenantId)->orderBy('bereich')->orderBy('id')->get();
    }
}
