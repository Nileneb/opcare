<?php

namespace App\Livewire\Quality;

use App\Domains\Quality\Services\IndicatorService;
use App\Domains\Quality\Support\Cohort;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QualityReport extends Component
{
    public string $stichtag;

    public string $von;

    public string $bis;

    public array $ergebnisse = [];

    public int $kohorte = 0;

    public function mount(): void
    {
        // WHY: Controlling/QMS ist Leitungs-Sicht — nicht für pflegehilfskraft/leserecht. mount() blockt auch den Livewire-Update-Pfad (ohne erfolgreichen mount kein Snapshot).
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );

        $this->stichtag = today()->toDateString();
        $this->von = today()->startOfQuarter()->toDateString();
        $this->bis = today()->endOfQuarter()->toDateString();
    }

    public function berechnen(IndicatorService $svc): void
    {
        $this->validate([
            'stichtag' => ['required', 'date'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ]);
        $cohort = Cohort::atStichtag($this->stichtag);
        $this->kohorte = $cohort->count();
        $this->ergebnisse = collect($svc->allIncidences($this->von, $this->bis, $cohort))
            ->map(fn ($r) => ['indicator' => $r->indicator, 'betroffene' => $r->betroffene, 'quote' => $r->quote()])
            ->all();
    }

    public function render()
    {
        return view('livewire.quality.quality-report');
    }
}
