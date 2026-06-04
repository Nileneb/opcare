<?php

namespace App\Domains\Quality\Services;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Quality\Data\IndicatorResult;
use App\Domains\Quality\Data\KpiSnapshot;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

class IndicatorService
{
    public function incidence(QualityIndicator $indicator, string $von, string $bis, Cohort $cohort): IndicatorResult
    {
        $betroffene = CareEvent::query()
            ->where('indicator', $indicator->value)
            ->whereIn('resident_id', $cohort->ids())
            ->whereBetween('datum', [$von, $bis])
            ->distinct('resident_id')->count('resident_id');

        return new IndicatorResult($indicator->value, 'inzidenz', $betroffene, $cohort->count());
    }

    public function prevalence(QualityIndicator $indicator, Cohort $cohort): IndicatorResult
    {
        $betroffene = CareEvent::query()
            ->where('indicator', $indicator->value)
            ->whereIn('resident_id', $cohort->ids())
            ->whereDate('datum', '<=', $cohort->stichtag)
            ->where(fn ($q) => $q->whereNull('behoben_am')->orWhereDate('behoben_am', '>', $cohort->stichtag))
            ->distinct('resident_id')->count('resident_id');

        return new IndicatorResult($indicator->value, 'praevalenz', $betroffene, $cohort->count());
    }

    public function allIncidences(string $von, string $bis, Cohort $cohort): array
    {
        return array_map(fn ($i) => $this->incidence($i, $von, $bis, $cohort), QualityIndicator::cases());
    }

    public function kpis(): KpiSnapshot
    {
        $aktive = Resident::where('status', 'aktiv')->get();
        $verteilung = $aktive->groupBy('pflegegrad')->map->count()
            ->mapWithKeys(fn ($n, $pg) => [(int) $pg => $n])->all();

        return new KpiSnapshot(
            bewohnerAktiv: $aktive->count(),
            pflegegradVerteilung: $verteilung,
            betten: (int) Room::sum('betten'),
            belegt: $aktive->whereNotNull('room_id')->count(),
        );
    }
}
