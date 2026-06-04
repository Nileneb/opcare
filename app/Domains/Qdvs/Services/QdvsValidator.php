<?php

namespace App\Domains\Qdvs\Services;

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Data\ValidationIssue;

class QdvsValidator
{
    /**
     * @param  array<int, QdvsResidentPackage>  $packages
     * @return array<int, ValidationIssue>
     */
    public function validate(array $packages): array
    {
        $issues = [];
        foreach ($packages as $p) {
            if ($p->pflegegrad === null || $p->pflegegrad < 1 || $p->pflegegrad > 5) {
                $issues[] = new ValidationIssue($p->pseudonym, 'pflegegrad', 'Pflegegrad fehlt oder ungültig (1–5).', 'fehler');
            }
            if (! $p->geburtsjahr) {
                $issues[] = new ValidationIssue($p->pseudonym, 'geburtsjahr', 'Geburtsjahr fehlt.', 'fehler');
            }
            if (! in_array($p->geschlecht, ['m', 'w', 'd'], true)) {
                $issues[] = new ValidationIssue($p->pseudonym, 'geschlecht', 'Geschlecht fehlt/ungültig.', 'fehler');
            }
            if (empty($p->icd_codes)) {
                $issues[] = new ValidationIssue($p->pseudonym, 'icd_codes', 'Keine Diagnose hinterlegt.', 'warnung');
            }
        }

        return $issues;
    }

    /** @param array<int, ValidationIssue> $issues */
    public function hatBlockierendeFehler(array $issues): bool
    {
        return collect($issues)->contains(fn (ValidationIssue $i) => $i->schwere === 'fehler');
    }
}
