<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\PrescriptionSchedule;
use App\Domains\Medication\Support\TimeslotClock;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class GenerateAdministrations
{
    // WHY(idempotenz): ein nächtlicher Job kann den Stellplan rollierend für die nächsten N Tage
    // materialisieren, ohne Duplikate. Eindeutiger Lookup: (prescription_schedule_id, soll_zeitpunkt, tageszeit).
    // WHY: Idempotenz über PHP-Exists-Check; setzt einen serialisierten täglichen Scheduler voraus (kein paralleler Doppellauf).
    public function handle(PrescriptionSchedule $schedule, string $von, string $bis): int
    {
        if ($schedule->frequenz === ScheduleFrequency::BeiBedarf) {
            return 0;
        }

        $rx = $schedule->prescription;
        $start = Carbon::parse($von)->max(Carbon::parse($rx->gueltig_von));
        $ende = Carbon::parse($bis);
        if ($rx->gueltig_bis) {
            $ende = $ende->min(Carbon::parse($rx->gueltig_bis));
        }
        if ($start->gt($ende)) {
            return 0;
        }

        $created = 0;
        foreach (CarbonPeriod::create($start->startOfDay(), $ende->startOfDay()) as $tag) {
            if (! $this->trifftZu($schedule, $tag)) {
                continue;
            }
            foreach (AdministrationTimeslot::scheduled() as $slot) {
                $menge = $schedule->dosis[$slot->value] ?? 0;
                if ($menge <= 0) {
                    continue;
                }
                [$h, $m] = explode(':', TimeslotClock::for($slot));
                $soll = $tag->copy()->setTime((int) $h, (int) $m, 0);

                $exists = MedicationAdministration::where('prescription_schedule_id', $schedule->id)
                    ->where('soll_zeitpunkt', $soll)
                    ->where('tageszeit', $slot->value)
                    ->exists();
                if ($exists) {
                    continue;
                }

                MedicationAdministration::create([
                    'resident_id' => $rx->resident_id,
                    'prescription_schedule_id' => $schedule->id,
                    'soll_zeitpunkt' => $soll,
                    'tageszeit' => $slot,
                    'dosis' => $menge,
                    'status' => AdministrationStatus::Geplant,
                ]);
                $created++;
            }
        }

        return $created;
    }

    private function trifftZu(PrescriptionSchedule $schedule, Carbon $tag): bool
    {
        return match ($schedule->frequenz) {
            ScheduleFrequency::Taeglich => true,
            ScheduleFrequency::Woechentlich => in_array($tag->dayOfWeekIso, $schedule->wochentage ?? [], true),
            ScheduleFrequency::Monatlich => $tag->day === ($schedule->wochentage[0] ?? 1),
            default => false,
        };
    }
}
