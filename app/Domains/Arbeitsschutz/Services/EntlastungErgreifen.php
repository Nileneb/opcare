<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Arbeitsschutz\Enums\Massnahmentyp;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Arbeitsschutz\Models\Schutzmassnahme;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Verknüpft eine Belastungsmeldung mit einer dokumentierten Schutzmaßnahme an der GBU.
 * Norm-Anker: § 3 Abs. 1 / § 4 ArbSchG (TOP-Maßnahmen), § 6 ArbSchG (Dokumentation).
 */
class EntlastungErgreifen
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * Find-or-create die PsychischeBelastung-Gefaehrdung an der GBU, legt darauf eine
     * Schutzmassnahme (Organisatorisch) an und setzt meldung.schutzmassnahme_id.
     */
    public function handle(
        Belastungsmeldung $meldung,
        Gefaehrdungsbeurteilung $gbu,
        string $beschreibung,
        ?string $frist = null,
    ): Schutzmassnahme {
        abort_unless($gbu->tenant_id === $this->currentTenant->id(), 403);

        return DB::transaction(function () use ($meldung, $gbu, $beschreibung, $frist): Schutzmassnahme {
            $gefaehrdung = Gefaehrdung::firstOrCreate(
                [
                    'tenant_id' => $gbu->tenant_id,
                    'gefaehrdungsbeurteilung_id' => $gbu->id,
                    'faktor' => Gefaehrdungsfaktor::PsychischeBelastung,
                ],
                [
                    'beschreibung' => 'Arbeitsbelastung (live erfasst)',
                    'wahrscheinlichkeit' => 2,
                    'schwere' => 2,
                ],
            );

            $massnahme = Schutzmassnahme::create([
                'tenant_id' => $gbu->tenant_id,
                'gefaehrdung_id' => $gefaehrdung->id,
                'typ' => Massnahmentyp::Organisatorisch,
                'beschreibung' => $beschreibung,
                'frist' => $frist,
            ]);

            $meldung->update(['schutzmassnahme_id' => $massnahme->id]);

            return $massnahme;
        });
    }
}
