<?php

namespace App\Livewire\Quality;

use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Controlling extends Component
{
    public function mount(): void
    {
        // WHY: Controlling/QMS ist Leitungs-Sicht — nicht für pflegehilfskraft/leserecht. mount() blockt auch den Livewire-Update-Pfad (ohne erfolgreichen mount kein Snapshot).
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );
    }

    public function render(IndicatorService $svc)
    {
        $cohort = Cohort::atStichtag(today()->toDateString());

        return view('livewire.quality.controlling', [
            'kpi' => $svc->kpis(),
            'incidences' => $svc->allIncidences(today()->subMonths(3)->toDateString(), today()->toDateString(), $cohort),
        ]);
    }
}
