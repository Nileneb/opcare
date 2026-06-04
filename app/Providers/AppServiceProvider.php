<?php

namespace App\Providers;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Assessment\Policies\AssessmentPolicy;
use App\Domains\Assessment\Policies\InstrumentPolicy;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\CarePlanning\Policies\CareReportPolicy;
use App\Domains\CarePlanning\Policies\SisAssessmentPolicy;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Policies\TenantPolicy;
use App\Domains\Identity\Policies\UserPolicy;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Policies\BuildingPolicy;
use App\Domains\Masterdata\Policies\ResidentPolicy;
use App\Domains\Medication\Models\MedicationAdministration;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Policies\MedicationAdministrationPolicy;
use App\Domains\Medication\Policies\PrescriptionPolicy;
use App\Domains\Quality\Models\CareEvent;
use App\Domains\Quality\Policies\CareEventPolicy;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Policies\CalendarEventPolicy;
use App\Domains\Scheduling\Policies\ShiftPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
    }

    public function boot(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Building::class, BuildingPolicy::class);
        Gate::policy(Resident::class, ResidentPolicy::class);
        Gate::policy(SisAssessment::class, SisAssessmentPolicy::class);
        Gate::policy(CareReport::class, CareReportPolicy::class);
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
        Gate::policy(MedicationAdministration::class, MedicationAdministrationPolicy::class);
        Gate::policy(CareEvent::class, CareEventPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(Instrument::class, InstrumentPolicy::class);
        Gate::before(fn ($user) => $user?->isSuperAdmin() ? true : null);
    }
}
