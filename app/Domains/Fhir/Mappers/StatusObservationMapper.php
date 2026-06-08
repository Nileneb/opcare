<?php

namespace App\Domains\Fhir\Mappers;

use App\Domains\Masterdata\Models\ResidentStatusObservation;
use App\Domains\Masterdata\Support\StatusObservationCatalog;
use Illuminate\Support\Carbon;

/**
 * Mappt erfasste Status-Beobachtungen (ResidentStatusObservation) auf ihre konkreten ÜLB-Observation-Profile:
 * Bewusstsein→Cognitive_Awareness, Harn/Stuhl→Continence_Differentiated_Assessment, Atmung→Qualitative_Breathing.
 * Profil, fixe code.coding und Wert-Vokabular kommen aus dem StatusObservationCatalog (Single Source).
 * Ernährung wird als Presence-Sektion aggregiert (nutritionPresence).
 */
class StatusObservationMapper
{
    private const SNOMED = StatusObservationCatalog::SNOMED;

    private const SNOMED_VERSION = StatusObservationCatalog::SNOMED_VERSION;

    /**
     * Eine codierte/text Status-Observation. Liefert null, wenn der Typ kein per-Eintrag-ÜLB-Profil hat
     * (z. B. Ernährung → aggregiert) oder der Wert für das Wert-Format fehlt.
     *
     * @return array{id:string, slice:string, resource:array<string,mixed>}|null
     */
    public function build(ResidentStatusObservation $obs, string $patientReference, string $performerReference, string $fallbackDate): ?array
    {
        $def = StatusObservationCatalog::get($obs->typ);
        if ($def === null || ! isset($def['profile'], $def['slice'], $def['fhir_code'])) {
            return null;
        }

        // WHY(FHIR): Resource-ids erlauben nur [A-Za-z0-9.-]; Katalog-Keys mit „_" würden eine
        // ungültige id erzeugen → Unterstrich auf Bindestrich normalisieren.
        $id = 'status-'.str_replace('_', '-', $obs->typ).'-'.$obs->id;
        $effective = $obs->erfasst_am?->toIso8601String() ?? $fallbackDate;
        $resource = [
            'resourceType' => 'Observation',
            'id' => $id,
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_'.$def['profile'].'|1.0.0']],
            'status' => 'final',
            'code' => ['coding' => [$this->coding($def['fhir_code'][0], $def['fhir_code'][1])]],
            'subject' => ['reference' => $patientReference],
            'performer' => [['reference' => $performerReference]],
        ];

        // WHY(ÜLB): Zeitpunkt-Profile (Last_Micturition/Last_Bowel_Movement) verbieten effective[x] (max=0) —
        // der Zeitpunkt steckt in valueDateTime. Ableitungs-Profile (Urinary/Fecal_Drainage) tragen das
        // Anlagedatum als effectivePeriod.start mit Pflicht-Extension Insertion_Date (fixes Label-Coding) auf
        // dem .start-Primitive. Andere Profile binden effective[x] teils auf Period statt dateTime.
        if ($def['kind'] === 'datetime') {
            // kein effective[x]
        } elseif ($def['kind'] === 'coded_insertion_date') {
            if (($obs->wert_text ?? '') !== '') {
                $resource['effectivePeriod'] = [
                    'start' => Carbon::parse((string) $obs->wert_text)->toIso8601String(),
                    '_start' => ['extension' => [[
                        'url' => 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Insertion_Date_Fecal_Urinary_Drainage',
                        'valueCodeableConcept' => ['coding' => [$this->coding(
                            '439272007:704321009=107733003',
                            'Date of procedure (observable entity) : Characterizes (attribute) = Introduction procedure (procedure)'
                        )]],
                    ]]],
                ];
            }
        } elseif (($def['effective'] ?? 'dateTime') === 'period') {
            $resource['effectivePeriod'] = ['start' => $effective];
        } else {
            $resource['effectiveDateTime'] = $effective;
        }

        if ($def['kind'] === 'text') {
            if (($obs->wert_text ?? '') === '') {
                return null;
            }
            $resource['valueString'] = (string) $obs->wert_text;
        } elseif ($def['kind'] === 'codeable_text') {
            // WHY(ÜLB): Profile wie Wish/Striking_Behavior binden value auf CodeableConcept, erlauben aber
            // coding-frei (nur .text) — Freitext-Erfassung ohne erzwungene SNOMED-Codierung.
            if (($obs->wert_text ?? '') === '') {
                return null;
            }
            $resource['valueCodeableConcept'] = ['text' => (string) $obs->wert_text];
        } elseif ($def['kind'] === 'datetime') {
            // WHY(ÜLB): Last_Micturition/Last_Bowel_Movement binden value auf dateTime (Zeitpunkt).
            // Carbon normalisiert die UI-Eingabe (datetime-local) auf ein FHIR-konformes dateTime
            // (mit Sekunden + Zeitzone — sonst Constraint-Verstoß).
            if (($obs->wert_text ?? '') === '') {
                return null;
            }
            $resource['valueDateTime'] = Carbon::parse((string) $obs->wert_text)->toIso8601String();
        } else {
            $display = $def['value_displays'][$obs->wert_code] ?? null;
            if ($obs->wert_code === null || $display === null) {
                return null;
            }
            $resource['valueCodeableConcept'] = ['coding' => [$this->coding((string) $obs->wert_code, $display)]];
        }

        return ['id' => $id, 'slice' => $def['slice'], 'resource' => $resource];
    }

    /**
     * Aggregierte Ernährungs-Presence-Observation (ÜLB-Sektion ernaehrung) — „Ernährungsbefund vorhanden",
     * sobald Kostform/Ernährungsform erfasst ist. Das konkrete Detail steht im Composition-Narrativ.
     *
     * @return array{id:string, slice:string, resource:array<string,mixed>}
     */
    public function nutritionPresence(int $residentId, string $patientReference, string $performerReference, string $date): array
    {
        $id = 'status-ernaehrung-'.$residentId;

        return [
            'id' => $id,
            'slice' => 'ernaehrung',
            'resource' => [
                'resourceType' => 'Observation',
                'id' => $id,
                'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_Observation_Presence_Information_Nutrition|1.0.0']],
                'status' => 'final',
                'code' => ['coding' => [$this->coding('364393001:704321009=384760004', 'Nutritional observable (observable entity) : Characterizes (attribute) = Feeding and dietary regime (regime/therapy)')]],
                'subject' => ['reference' => $patientReference],
                'effectiveDateTime' => $date,
                'performer' => [['reference' => $performerReference]],
                'valueCodeableConcept' => ['coding' => [$this->coding('373573001:246090004=300893006', 'Clinical finding present (situation) : Associated finding (attribute) = Nutritional finding (finding)')]],
            ],
        ];
    }

    /** @return array<string, string> */
    private function coding(string $code, string $display): array
    {
        return ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => $code, 'display' => $display];
    }
}
