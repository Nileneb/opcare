<?php

namespace App\Domains\Qdvs\Services;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;
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

        $gewichte = VitalReading::query()
            ->whereIn('resident_id', $cohort->ids())
            ->where('typ', VitalType::Gewicht)
            ->whereDate('gemessen_am', '<=', $cohort->stichtag)
            ->orderByDesc('gemessen_am')
            ->get()
            ->groupBy('resident_id');

        return $residents->map(function (Resident $r) use ($aktive, $gewichte, $cohort) {
            $vorhanden = ($aktive[$r->id] ?? collect())->pluck('indicator')
                ->map(fn ($i) => $i instanceof QualityIndicator ? $i->value : $i)->all();

            $indikatoren = [];
            foreach (QualityIndicator::cases() as $i) {
                $indikatoren[$i->value] = in_array($i->value, $vorhanden, true);
            }

            $gewicht = ($gewichte[$r->id] ?? collect())->first();

            return new QdvsResidentPackage(
                pseudonym: 'R-'.$r->id,
                geburtsjahr: $r->geburtsdatum?->year,
                geschlecht: $r->geschlecht,
                pflegegrad: $r->pflegegrad,
                aufnahme_am: $r->aufnahme_am?->toDateString(),
                icd_codes: $r->diagnoses->pluck('icdCode.code')->filter()->values()->all(),
                indikatoren: $indikatoren,
                geburtsmonat: $r->geburtsdatum?->month,
                gewicht_kg: $gewicht?->wert !== null ? (float) $gewicht->wert : null,
                gewicht_datum: $gewicht?->gemessen_am?->toDateString(),
                auszug_am: $r->entlassung_am?->toDateString(),
                erhebungsdatum: $cohort->stichtag,
            );
        })->all();
    }
}
