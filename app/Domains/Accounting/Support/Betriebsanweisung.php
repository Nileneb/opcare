<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Enums\GhsPiktogramm;
use App\Domains\Accounting\Models\Gefahrstoff;

/**
 * Assembliert die 6 TRGS-555-Sektionen einer § 14 GefStoffV-Betriebsanweisung aus dem Gefahrstoff-Datensatz.
 * Reine Daten-Assemblierung — kein HTML, kein I/O.
 */
class Betriebsanweisung
{
    /**
     * Baut die 6 TRGS-555-Sektionen für den gegebenen Gefahrstoff.
     *
     * @return array{
     *   bezeichnung: string,
     *   arbeitsbereich: string|null,
     *   lagerort: string|null,
     *   signalwort: string|null,
     *   piktogramme: list<string>,
     *   h_saetze: list<string>,
     *   p_saetze: list<string>,
     *   schutzmassnahmen: string|null,
     *   stoerfall: string|null,
     *   erste_hilfe: string|null,
     *   entsorgung: string|null,
     *   stand: string,
     *   unterweisung_intervall: int,
     * }
     */
    public static function fuer(Gefahrstoff $g): array
    {
        $piktogrammLabels = array_values(array_map(
            static fn (string $code) => GhsPiktogramm::tryFrom($code)?->label() ?? $code,
            $g->ghs_piktogramme ?? [],
        ));

        $stand = $g->sdb_version_datum
            ? $g->sdb_version_datum->format('d.m.Y')
            : ($g->updated_at?->format('d.m.Y') ?? '—');

        return [
            'bezeichnung' => $g->artikel->name,
            'arbeitsbereich' => $g->arbeitsbereiche,
            'lagerort' => $g->lagerort,
            'signalwort' => $g->signalwort,
            'piktogramme' => $piktogrammLabels,
            'h_saetze' => $g->h_saetze ?? [],
            'p_saetze' => $g->p_saetze ?? [],
            'schutzmassnahmen' => $g->schutzmassnahmen,
            'stoerfall' => $g->stoerfall_massnahmen,
            'erste_hilfe' => $g->erste_hilfe,
            'entsorgung' => $g->entsorgung,
            'stand' => $stand,
            'unterweisung_intervall' => $g->unterweisung_intervall_monate,
        ];
    }
}
