<?php

namespace App\Domains\Qdvs\Services;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;
use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Support\Cohort;
use Illuminate\Support\Collection;

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
            $events = $aktive[$r->id] ?? collect();
            $vorhanden = $events->pluck('indicator')
                ->map(fn ($i) => $i instanceof QualityIndicator ? $i->value : $i)->all();

            $indikatoren = [];
            foreach (QualityIndicator::cases() as $i) {
                $indikatoren[$i->value] = in_array($i->value, $vorhanden, true);
            }

            $dekubitus = $events->first(fn ($e) => ($e->indicator instanceof QualityIndicator ? $e->indicator->value : $e->indicator) === QualityIndicator::Dekubitus->value);
            $dekDetails = is_array($dekubitus?->details) ? $dekubitus->details : [];

            $stuerze = $events->filter(fn ($e) => ($e->indicator instanceof QualityIndicator ? $e->indicator->value : $e->indicator) === QualityIndicator::Sturz->value);
            [$sturzAnzahl, $sturzfolgen] = $this->sturz($stuerze);

            $gewicht = ($gewichte[$r->id] ?? collect())->first();

            return new QdvsResidentPackage(
                pseudonym: 'R-'.$r->id,
                geburtsjahr: $r->geburtsdatum->year,
                geschlecht: $r->geschlecht,
                pflegegrad: $r->pflegegrad,
                aufnahme_am: $r->aufnahme_am->toDateString(),
                icd_codes: $r->diagnoses->pluck('icdCode.code')->filter()->values()->all(),
                indikatoren: $indikatoren,
                geburtsmonat: $r->geburtsdatum->month,
                gewicht_kg: $gewicht?->wert !== null ? (float) $gewicht->wert : null,
                gewicht_datum: $gewicht?->gemessen_am?->toDateString(),
                auszug_am: $r->entlassung_am?->toDateString(),
                erhebungsdatum: $cohort->stichtag,
                dekubitus_stadium: isset($dekDetails['stadium']) ? (int) $dekDetails['stadium'] : null,
                dekubitus_beginn: $dekDetails['beginn'] ?? null,
                dekubitus_ende: $dekDetails['ende'] ?? null,
                sturz_anzahl: $sturzAnzahl,
                sturzfolgen: $sturzfolgen,
            );
        })->all();
    }

    /**
     * Leitet DAS-Feld 71 (STURZ) + 72 (STURZFOLGEN) aus den aktiven Sturz-Ereignissen ab.
     * Mehr als ein Ereignis ⇒ „mehrmals" (2); Fraktur in irgendeinem Ereignis ⇒ Folge-Code 1, sonst 0.
     *
     * @param  Collection<int, CareEvent>  $stuerze
     * @return array{0: ?int, 1: array<int, int>}
     */
    private function sturz($stuerze): array
    {
        if ($stuerze->isEmpty()) {
            return [0, []];
        }

        $anzahl = $stuerze->count() > 1 ? 2 : (int) (($stuerze->first()->details['anzahl'] ?? 1));
        $fraktur = $stuerze->contains(fn ($e) => is_array($e->details) && ! empty($e->details['fraktur']));

        return [max(1, min(2, $anzahl)), [$fraktur ? 1 : 0]];
    }
}
