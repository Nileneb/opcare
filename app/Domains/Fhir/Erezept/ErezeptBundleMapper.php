<?php

namespace App\Domains\Fhir\Erezept;

use App\Domains\Fhir\Support\GermanAddress;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\ResidentInsurance;
use App\Domains\Medication\Models\Prescription;
use Illuminate\Support\Carbon;

/**
 * Prescription → KBV-E-Rezept-Verordnungsbundle `KBV_PR_ERP_Bundle|1.3` (Muster 16, Datenebene).
 * Konformität gegen den offiziellen gematik Referenzvalidator (Modul erp). Struktur 1:1 aus der
 * KBV-/DAV-Beispielverordnung (PZN-Verordnung) übernommen; opcare-Daten eingesetzt (Patient/KVNR,
 * Practitioner/LANR, Organization/BSNR, Coverage, Medication/PZN, MedicationRequest).
 *
 * WHY(Track C): opcare verordnet nicht selbst — die HBA-Signatur + Fachdienst-Übertragung (echte
 * PrescriptionId, Prüfnummer) sind die Anschluss-Ebene. Dies ist die FHIR-Daten-Repräsentation.
 *
 * @see docs/ti2.0/ — Konnektoren-Vision
 */
class ErezeptBundleMapper
{
    private const KBV = 'https://fhir.kbv.de/StructureDefinition/';

    private const KBV_CS = 'https://fhir.kbv.de/CodeSystem/';

    public function build(Prescription $p, Physician $arzt, ResidentInsurance $ins): array
    {
        $r = $p->resident;
        $m = $p->medProduct;
        $base = rtrim(config('app.url'), '/').'/fhir/erezept/';
        $date = Carbon::now()->toIso8601String();
        $authored = $p->gueltig_von->toDateString();

        $patientId = 'erezept-patient-'.$r->id;
        $practitionerId = 'erezept-practitioner-'.$arzt->id;
        $orgId = 'erezept-organization-'.$arzt->id;
        $coverageId = 'erezept-coverage-'.$ins->id;
        $medId = 'erezept-medication-'.$m->id;
        $reqId = 'erezept-prescription-'.$p->id;
        $compId = 'erezept-composition-'.$p->id;

        $ref = fn (string $type, string $id) => ['reference' => $type.'/'.$id];

        $composition = [
            'resourceType' => 'Composition',
            'id' => $compId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_ERP_Composition|1.3']],
            'extension' => [[
                'url' => self::KBV.'KBV_EX_FOR_Legal_basis',
                'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_STATUSKENNZEICHEN', 'code' => '00'],
            ]],
            'status' => 'final',
            'type' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_FORMULAR_ART', 'code' => 'e16A']]],
            'subject' => $ref('Patient', $patientId),
            'date' => $date,
            'author' => [
                ['reference' => 'Practitioner/'.$practitionerId, 'type' => 'Practitioner'],
                ['type' => 'Device', 'identifier' => ['system' => self::KBV.'../NamingSystem/KBV_NS_FOR_Pruefnummer', 'value' => 'Y/400/1910/36/346']],
            ],
            'title' => 'elektronische Arzneimittelverordnung',
            'custodian' => $ref('Organization', $orgId),
            'section' => [
                ['code' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_ERP_Section_Type', 'code' => 'Prescription']]], 'entry' => [$ref('MedicationRequest', $reqId)]],
                ['code' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_ERP_Section_Type', 'code' => 'Coverage']]], 'entry' => [$ref('Coverage', $coverageId)]],
            ],
        ];
        // Prüfnummer-NamingSystem korrekt setzen (ohne den KBV-StructureDefinition-Pfad).
        $composition['author'][1]['identifier']['system'] = 'https://fhir.kbv.de/NamingSystem/KBV_NS_FOR_Pruefnummer';

        $medicationRequest = [
            'resourceType' => 'MedicationRequest',
            'id' => $reqId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_ERP_Prescription|1.3']],
            'extension' => [
                ['url' => self::KBV.'KBV_EX_FOR_StatusCoPayment', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_FOR_StatusCoPayment', 'code' => '0']],
                ['url' => self::KBV.'KBV_EX_ERP_EmergencyServicesFee', 'valueBoolean' => false],
                ['url' => self::KBV.'KBV_EX_FOR_SER', 'valueBoolean' => false],
                ['url' => self::KBV.'KBV_EX_ERP_Multiple_Prescription', 'extension' => [['url' => 'Kennzeichen', 'valueBoolean' => false]]],
            ],
            'status' => 'active',
            'intent' => 'order',
            'medicationReference' => $ref('Medication', $medId),
            'subject' => $ref('Patient', $patientId),
            'authoredOn' => $authored,
            'requester' => $ref('Practitioner', $practitionerId),
            'insurance' => [$ref('Coverage', $coverageId)],
            'dosageInstruction' => [[
                'extension' => [['url' => self::KBV.'KBV_EX_ERP_DosageFlag', 'valueBoolean' => true]],
                'text' => $this->dosage($p),
            ]],
            'dispenseRequest' => ['quantity' => ['value' => 1, 'unit' => 'Packung']],
            'substitution' => ['allowedBoolean' => true],
        ];

        $medication = [
            'resourceType' => 'Medication',
            'id' => $medId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_ERP_Medication_PZN|1.3']],
            'extension' => [
                ['url' => self::KBV.'KBV_EX_ERP_Medication_Category', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_ERP_Medication_Category', 'code' => '00']],
                ['url' => self::KBV.'KBV_EX_Base_Medication_Type', 'valueCodeableConcept' => ['coding' => [['system' => 'http://snomed.info/sct', 'version' => 'http://snomed.info/sct/11000274103/version/20240515', 'code' => '763158003', 'display' => 'Medicinal product (product)']]]],
                ['url' => self::KBV.'KBV_EX_ERP_Medication_Vaccine', 'valueBoolean' => false],
                ['url' => 'http://fhir.de/StructureDefinition/normgroesse', 'valueCode' => 'N3'],
            ],
            'code' => [
                'coding' => [['system' => 'http://fhir.de/CodeSystem/ifa/pzn', 'code' => $m->pzn]],
                'text' => trim($m->name.' '.($m->staerke ?? '')),
            ],
            'form' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_DARREICHUNGSFORM', 'code' => 'TAB']]],
            'ingredient' => [[
                'itemCodeableConcept' => ['text' => $m->wirkstoff ?: $m->name],
                'strength' => $this->strength($m->staerke),
            ]],
        ];

        $patient = [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_FOR_Patient|1.2']],
            'identifier' => [[
                'type' => ['coding' => [['system' => 'http://fhir.de/CodeSystem/identifier-type-de-basis', 'code' => 'KVZ10']]],
                'system' => 'http://fhir.de/sid/gkv/kvid-10',
                'value' => $ins->versichertennr,
            ]],
            'name' => $this->name($r->name),
            'birthDate' => $r->geburtsdatum->toDateString(),
            'address' => $this->address($r->strasse, $r->hausnummer, $r->plz, $r->ort),
        ];

        $practitioner = [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_FOR_Practitioner|1.2']],
            'identifier' => [[
                'type' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'LANR']]],
                'system' => 'https://fhir.kbv.de/NamingSystem/KBV_NS_Base_ANR',
                'value' => $arzt->lanr,
            ]],
            'name' => $this->name($arzt->name),
            'qualification' => [
                ['code' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_FOR_Qualification_Type', 'code' => '00']]]],
                ['code' => ['coding' => [['system' => self::KBV_CS.'KBV_CS_FOR_Berufsbezeichnung', 'code' => 'Berufsbezeichnung']], 'text' => $arzt->fachrichtung ?: 'Arzt']],
            ],
        ];

        $organization = [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_FOR_Organization|1.2']],
            'identifier' => [[
                'type' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'BSNR']]],
                'system' => 'https://fhir.kbv.de/NamingSystem/KBV_NS_Base_BSNR',
                'value' => $arzt->bsnr,
            ]],
            'name' => 'Praxis '.$arzt->name,
            'telecom' => [['system' => 'phone', 'value' => $arzt->kontakt ?: '0000000']],
            'address' => $this->address($arzt->strasse, $arzt->hausnummer, $arzt->plz, $arzt->ort),
        ];

        $coverage = [
            'resourceType' => 'Coverage',
            'id' => $coverageId,
            'meta' => ['profile' => [self::KBV.'KBV_PR_FOR_Coverage|1.2']],
            'extension' => [
                ['url' => 'http://fhir.de/StructureDefinition/gkv/besondere-personengruppe', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_PERSONENGRUPPE', 'code' => '00']],
                ['url' => 'http://fhir.de/StructureDefinition/gkv/dmp-kennzeichen', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_DMP', 'code' => '00']],
                ['url' => 'http://fhir.de/StructureDefinition/gkv/wop', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_SFHIR_ITA_WOP', 'code' => '03']],
                ['url' => 'http://fhir.de/StructureDefinition/gkv/versichertenart', 'valueCoding' => ['system' => self::KBV_CS.'KBV_CS_SFHIR_KBV_VERSICHERTENSTATUS', 'code' => '1']],
            ],
            'status' => 'active',
            'type' => ['coding' => [['system' => 'http://fhir.de/CodeSystem/versicherungsart-de-basis', 'code' => 'GKV']]],
            'beneficiary' => $ref('Patient', $patientId),
            'payor' => [[
                'identifier' => ['system' => 'http://fhir.de/sid/arge-ik/iknr', 'value' => $ins->healthInsurance->ik_nummer ?: '104212059'],
                'display' => $ins->healthInsurance->name ?: 'Krankenkasse',
            ]],
        ];

        $entry = fn (string $type, string $id, array $resource) => ['fullUrl' => $base.$type.'/'.$id, 'resource' => $resource];

        return [
            'resourceType' => 'Bundle',
            'id' => 'erezept-bundle-'.$p->id,
            'meta' => ['profile' => [self::KBV.'KBV_PR_ERP_Bundle|1.3']],
            'identifier' => ['system' => 'https://gematik.de/fhir/erp/NamingSystem/GEM_ERP_NS_PrescriptionId', 'value' => '160.000.764.737.300.50'],
            'type' => 'document',
            'timestamp' => $date,
            'entry' => [
                $entry('Composition', $compId, $composition),
                $entry('MedicationRequest', $reqId, $medicationRequest),
                $entry('Medication', $medId, $medication),
                $entry('Patient', $patientId, $patient),
                $entry('Practitioner', $practitionerId, $practitioner),
                $entry('Organization', $orgId, $organization),
                $entry('Coverage', $coverageId, $coverage),
            ],
        ];
    }

    private function dosage(Prescription $p): string
    {
        $d = data_get($p->schedules->first(), 'dosis', []);

        return collect(['morgens', 'mittags', 'abends', 'nachts'])
            ->map(fn ($t) => (string) ($d[$t] ?? 0))
            ->implode('-');
    }

    /**
     * Zerlegt den Namen in family/given/prefix. WHY(KBV): name.given ist max 1 → Titel (Dr./Prof./…)
     * werden als prefix abgespalten, der erste verbleibende Token ist der (einzige) Vorname.
     *
     * @return array<int, array<string, mixed>>
     */
    private function name(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $family = array_pop($parts) ?? '';

        $prefix = [];
        while ($parts !== [] && preg_match('/^(Dr\.?|Prof\.?|med\.?|Dipl\.?-?\w*)$/i', $parts[0])) {
            $prefix[] = array_shift($parts);
        }
        $given = $parts !== [] ? $parts[0] : 'NN';

        $name = [
            'use' => 'official',
            'family' => $family,
            '_family' => ['extension' => [['url' => 'http://hl7.org/fhir/StructureDefinition/humanname-own-name', 'valueString' => $family]]],
            'given' => [$given],
        ];
        if ($prefix !== []) {
            $name['prefix'] = [implode(' ', $prefix)];
            $name['_prefix'] = [['extension' => [['url' => 'http://hl7.org/fhir/StructureDefinition/iso21090-EN-qualifier', 'valueCode' => 'AC']]]];
        }

        return [$name];
    }

    /**
     * Baut eine FHIR-Adresse aus echten Stammdaten; fehlende Felder fallen auf Platzhalter zurück
     * (E-Rezept erzwingt eine Adresse — s. docs/INBETRIEBNAHME.md §3, Adress-Stammdaten).
     *
     * @return array<int, array<string, mixed>>
     */
    private function address(?string $strasse, ?string $hausnummer, ?string $plz, ?string $ort): array
    {
        // E-Rezept erzwingt eine vollständige Adresse → Platzhalter füllen leere Felder, bevor der
        // geteilte KBV-Adress-Builder (GermanAddress) sie strukturiert.
        return GermanAddress::kbv(
            $strasse ?: 'Musterstr.',
            $hausnummer ?: '1',
            $plz ?: '12345',
            $ort ?: 'Musterstadt',
        ) ?? [];
    }

    /**
     * Parst eine Stärke wie "5 mg" in eine Ratio (numerator value+unit / denominator 1 Stk).
     *
     * @return array<string, mixed>
     */
    private function strength(?string $staerke): array
    {
        preg_match('/([\d.,]+)\s*(\S+)?/', (string) $staerke, $m);
        $value = isset($m[1]) ? (float) str_replace(',', '.', $m[1]) : 1.0;
        $unit = $m[2] ?? 'Stk';

        return [
            'numerator' => ['value' => $value, 'unit' => $unit],
            'denominator' => ['value' => 1, 'unit' => 'Stk'],
        ];
    }
}
