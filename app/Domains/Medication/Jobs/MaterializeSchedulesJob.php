<?php

namespace App\Domains\Medication\Jobs;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Models\PrescriptionSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MaterializeSchedulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GenerateAdministrations $generate): void
    {
        $von = Carbon::today()->toDateString();
        $bis = Carbon::today()->addDays(7)->toDateString();

        PrescriptionSchedule::withoutGlobalScopes()
            ->with(['prescription', 'prescription.tenant'])
            ->whereHas('prescription', fn ($q) => $q->whereNull('abgesetzt_am'))
            ->each(function (PrescriptionSchedule $schedule) use ($generate, $von, $bis) {
                $tenant = $schedule->prescription->tenant ?? null;
                if ($tenant === null) {
                    return;
                }

                app(CurrentTenant::class)->set($tenant);
                $generate->handle($schedule, $von, $bis);
            });
    }
}
