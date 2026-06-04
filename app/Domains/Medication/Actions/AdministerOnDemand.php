<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\PrescriptionSchedule;
use DomainException;
use Illuminate\Support\Facades\DB;

class AdministerOnDemand
{
    public function __construct(private AdministerMedication $administer) {}

    public function handle(PrescriptionSchedule $schedule, int $userId, float $dosis, ?string $notiz = null): MedicationAdministration
    {
        return DB::transaction(function () use ($schedule, $userId, $dosis, $notiz) {
            $rx = $schedule->prescription;

            if ($schedule->max_anzahl_taeglich !== null) {
                $heute = MedicationAdministration::where('prescription_schedule_id', $schedule->id)
                    ->where('status', AdministrationStatus::Gegeben->value)
                    ->whereDate('ist_zeitpunkt', today())
                    ->count();
                if ($heute + 1 > (float) $schedule->max_anzahl_taeglich) {
                    throw new DomainException('Tageshöchstmenge der Bedarfsmedikation überschritten.');
                }
            }

            $gabe = MedicationAdministration::create([
                'resident_id' => $rx->resident_id,
                'prescription_schedule_id' => $schedule->id,
                'soll_zeitpunkt' => now(),
                'tageszeit' => AdministrationTimeslot::BeiBedarf,
                'dosis' => $dosis,
                'status' => AdministrationStatus::Geplant,
            ]);

            return $this->administer->handle($gabe, new AdministerData(
                quittiert_von: $userId, med_product_id: $rx->med_product_id, dosis: $dosis, notiz: $notiz,
            ));
        });
    }
}
