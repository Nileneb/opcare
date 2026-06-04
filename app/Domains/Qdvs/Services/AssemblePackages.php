<?php

namespace App\Domains\Qdvs\Services;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;

class AssemblePackages
{
    /** @return array<int, QdvsResidentPackage> */
    public function handle(Cohort $cohort): array
    {
        $residents = Resident::with('diagnoses.icdCode')->whereIn('id', $cohort->ids())->get();

        $aktive = CareEvent::query()
            ->whereIn('resident_id', $cohort->ids())
            ->whereDate('datum', '<=', $cohort->stichtag)
            ->where(fn ($q) => $q->whereNull('behoben_am')->orWhereDate('behoben_am', '>', $cohort->stichtag))
            ->get()
            ->groupBy('resident_id');

        return $residents->map(function (Resident $r) use ($aktive) {
            $vorhanden = ($aktive[$r->id] ?? collect())->pluck('indicator')
                ->map(fn ($i) => $i instanceof QualityIndicator ? $i->value : $i)->all();

            $indikatoren = [];
            foreach (QualityIndicator::cases() as $i) {
                $indikatoren[$i->value] = in_array($i->value, $vorhanden, true);
            }

            return new QdvsResidentPackage(
                pseudonym: 'R-'.$r->id,
                geburtsjahr: $r->geburtsdatum?->year,
                geschlecht: $r->geschlecht,
                pflegegrad: $r->pflegegrad,
                aufnahme_am: $r->aufnahme_am?->toDateString(),
                icd_codes: $r->diagnoses->pluck('icdCode.code')->filter()->values()->all(),
                indikatoren: $indikatoren,
            );
        })->all();
    }
}
