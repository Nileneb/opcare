<?php

namespace App\Domains\Fhir\Isip;

use App\Domains\Masterdata\Models\Resident;

/**
 * Resident-Aufenthalt → gematik ISiP `ISiPPflegeepisode` (Encounter). Konformität gegen den gematik
 * Referenzvalidator (Modul isip1). type trägt Kontaktebene (Einrichtungskontakt) + Pflegeart
 * (Langzeitpflege); period aus Aufnahme/Entlassung. Struktur aus der gematik-Beispielressource verifiziert.
 */
class IsipEncounterMapper
{
    public const PROFILE = 'https://gematik.de/fhir/isip/v1/Basismodul/StructureDefinition/ISiPPflegeepisode';

    public static function id(Resident $r): string
    {
        return 'isip-pflegeepisode-'.$r->id;
    }

    /** @return array<string, mixed> */
    public function map(Resident $r): array
    {
        $period = ['start' => $r->aufnahme_am->toDateString()];
        if ($r->entlassung_am !== null) {
            $period['end'] = $r->entlassung_am->toDateString();
        }

        return [
            'resourceType' => 'Encounter',
            'id' => self::id($r),
            'meta' => ['profile' => [self::PROFILE]],
            // WHY(ISiP): aktiver Aufenthalt → in-progress; entlassen → finished.
            'status' => $r->entlassung_am !== null ? 'finished' : 'in-progress',
            'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'IMP', 'display' => 'inpatient encounter'],
            // WHY(ISiK): Aufnahmenummer-Identifier mit Typ VN ist Pflicht (min 1).
            'identifier' => [[
                'type' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'VN']]],
                'system' => 'https://opcare.local/sid/fallnummer',
                'value' => (string) $r->id,
            ]],
            // WHY(ISiK): Kontaktebene-Slice ist per Pattern auf 'abteilungskontakt' fixiert; zusätzlich die
            // ISiP-Pflegeart (Langzeitpflege).
            'type' => [
                ['coding' => [['system' => 'http://fhir.de/CodeSystem/Kontaktebene', 'code' => 'abteilungskontakt', 'display' => 'Abteilungskontakt']]],
                ['coding' => [['system' => 'https://gematik.de/fhir/isip/v1/Basismodul/CodeSystem/EncounterPflegeArten', 'code' => 'langzeitpflege']]],
            ],
            'subject' => ['reference' => 'Patient/'.IsipPatientMapper::id($r)],
            'period' => $period,
        ];
    }
}
