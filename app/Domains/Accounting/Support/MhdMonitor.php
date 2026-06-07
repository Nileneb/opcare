<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Lagerschicht;
use Illuminate\Support\Collection;

/**
 * MHD-Überwachung für Lagerbestände.
 *
 * Aufbewahrungshinweis (BVL): Rückverfolgungsdokumente 5 Jahre aufbewahren;
 * bei kurz-MHD-Artikeln empfohlen MHD + 6 Monate. Kein Auto-Löschen — nur Doku-Hinweis.
 */
class MhdMonitor
{
    /**
     * Offene Schichten mit ablaufendem oder abgelaufenem MHD.
     *
     * @return Collection<int, array{schicht: Lagerschicht, artikel: string, mhd: string, abgelaufen: bool}>
     */
    public function ablaufend(int $tenantId, int $tageVorlauf = 14): Collection
    {
        // WHY(date-Cast-Falle): date-Cast speichert als '...00:00:00'; whereDate() vermeidet
        // das whereBetween-Bis-Tag-Problem (opcare-date-range-whereBetween-falle).
        $grenze = today()->addDays($tageVorlauf)->toDateString();

        return Lagerschicht::with('artikel')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('mhd')
            ->where('menge_rest', '>', 0)
            ->whereDate('mhd', '<=', $grenze)
            ->orderBy('mhd')
            ->get()
            ->map(function (Lagerschicht $schicht) {
                return [
                    'schicht' => $schicht,
                    'artikel' => $schicht->artikel->name,
                    'mhd' => $schicht->mhd->toDateString(),
                    'abgelaufen' => $schicht->mhd->lt(today()),
                ];
            });
    }
}
