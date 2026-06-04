<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\Prescription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DiscontinuePrescription
{
    public function handle(Prescription $rx, int $userId, ?string $ab = null): Prescription
    {
        return DB::transaction(function () use ($rx, $userId, $ab) {
            $stichtag = Carbon::parse($ab ?? now()->toDateString());

            $rx->update(['abgesetzt_am' => $stichtag->toDateString(), 'abgesetzt_von' => $userId]);

            MedicationAdministration::whereIn('prescription_schedule_id', $rx->schedules()->pluck('id'))
                ->where('status', AdministrationStatus::Geplant->value)
                ->where('soll_zeitpunkt', '>=', $stichtag->startOfDay())
                ->update(['status' => AdministrationStatus::Ausgelassen, 'notiz' => 'Verordnung abgesetzt']);

            return $rx;
        });
    }
}
