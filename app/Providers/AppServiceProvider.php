<?php

namespace App\Providers;

use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\CarePlanning\Policies\CareReportPolicy;
use App\Domains\CarePlanning\Policies\SisAssessmentPolicy;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Policies\BuildingPolicy;
use App\Domains\Masterdata\Policies\ResidentPolicy;
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
        Gate::policy(Building::class, BuildingPolicy::class);
        Gate::policy(Resident::class, ResidentPolicy::class);
        Gate::policy(SisAssessment::class, SisAssessmentPolicy::class);
        Gate::policy(CareReport::class, CareReportPolicy::class);
        Gate::before(fn ($user) => $user?->isSuperAdmin() ? true : null);
    }
}
