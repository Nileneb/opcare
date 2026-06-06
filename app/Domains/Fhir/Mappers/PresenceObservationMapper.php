<?php

namespace App\Domains\Fhir\Mappers;

/**
 * Generische ÜLB-„Presence/Information"-Observation: fixe code-Coding + Präsenz-value (CodeableConcept aus
 * gebundenem ValueSet) + naehereInformationen-Extensions, die auf die konformen Leaf-Ressourcen verweisen.
 * Deckt die Composition-Sektionen allergien / probleme / medikationsplan ab (gleiche Profilstruktur).
 */
class PresenceObservationMapper
{
    private const SNOMED = 'http://snomed.info/sct';

    private const SNOMED_VERSION = 'http://snomed.info/sct/900000000000207008/version/20220331';

    private const HAS_MEMBER_EXT = 'https://fhir.kbv.de/StructureDefinition/KBV_EX_MIO_ULB_Reference_Has_Member';

    /**
     * @param  array<int, string>  $memberRefs  Referenzen auf konforme AllergyIntolerance-Ressourcen
     * @return array<string, mixed>
     */
    public function allergies(string $id, string $patientReference, string $performerReference, string $date, array $memberRefs): array
    {
        return $this->build(
            $id, 'Observation_Presence_Allergies',
            $this->coding('363787002:704326004=420134006', 'Observable entity (observable entity) : Precondition (attribute) = Propensity to adverse reaction (finding)'),
            $this->coding('420134006:363713009=52101004', 'Propensity to adverse reaction (finding) : Has interpretation (attribute) = Present (qualifier value)'),
            $patientReference, $performerReference, $date, $memberRefs,
        );
    }

    /**
     * @param  array<int, string>  $memberRefs  Referenzen auf konforme Condition-Ressourcen
     * @return array<string, mixed>
     */
    public function problems(string $id, string $patientReference, string $performerReference, string $date, array $memberRefs): array
    {
        return $this->build(
            $id, 'Observation_Presence_Problems',
            $this->coding('363787002:704326004=(404684003:47429007=55607006)', 'Observable entity (observable entity) : Precondition (attribute) = ( Clinical finding (finding) : Associated with (attribute) = Problem (finding) )'),
            $this->coding('373573001:246090004=55607006', 'Clinical finding present (situation) : Associated finding (attribute) = Problem (finding)'),
            $patientReference, $performerReference, $date, $memberRefs,
        );
    }

    /**
     * @param  array<int, string>  $memberRefs  Referenzen auf konforme MedicationStatement-Ressourcen
     * @return array<string, mixed>
     */
    public function medicines(string $id, string $patientReference, string $performerReference, string $date, array $memberRefs): array
    {
        return $this->build(
            $id, 'Observation_Information_Medicines',
            $this->coding('363819003', 'Drug therapy observable (observable entity)'),
            $this->coding('309298003:363713009=52101004', 'Drug therapy finding (finding) : Has interpretation (attribute) = Present (qualifier value)'),
            $patientReference, $performerReference, $date, $memberRefs,
        );
    }

    /**
     * @param  array<int, string>  $memberRefs  Referenzen auf konforme Assessment_Free-Ressourcen
     * @return array<string, mixed>
     */
    public function functionalAssessment(string $id, string $patientReference, string $performerReference, string $date, array $memberRefs): array
    {
        return $this->build(
            $id, 'Observation_Presence_Functional_Assessment',
            $this->coding('363787002:704326004=105719004', 'Observable entity (observable entity) : Precondition (attribute) = Body disability AND/OR failure state (finding)'),
            $this->coding('373573001:246090004=105719004', 'Clinical finding present (situation) : Associated finding (attribute) = Body disability AND/OR failure state (finding)'),
            $patientReference, $performerReference, $date, $memberRefs,
        );
    }

    /**
     * @param  array<string, mixed>  $codeCoding
     * @param  array<string, mixed>  $valueCoding
     * @param  array<int, string>  $memberRefs
     * @return array<string, mixed>
     */
    private function build(string $id, string $profile, array $codeCoding, array $valueCoding, string $patientReference, string $performerReference, string $date, array $memberRefs): array
    {
        $obs = [
            'resourceType' => 'Observation',
            'id' => $id,
            'meta' => ['profile' => ['https://fhir.kbv.de/StructureDefinition/KBV_PR_MIO_ULB_'.$profile.'|1.0.0']],
            'status' => 'final',
            'code' => ['coding' => [$codeCoding]],
            'subject' => ['reference' => $patientReference],
            'effectiveDateTime' => $date,
            'performer' => [['reference' => $performerReference]],
            'valueCodeableConcept' => ['coding' => [$valueCoding]],
        ];
        foreach ($memberRefs as $ref) {
            $obs['extension'][] = ['url' => self::HAS_MEMBER_EXT, 'valueReference' => ['reference' => $ref]];
        }

        return $obs;
    }

    /** @return array<string, mixed> */
    private function coding(string $code, string $display): array
    {
        return ['system' => self::SNOMED, 'version' => self::SNOMED_VERSION, 'code' => $code, 'display' => $display];
    }
}
