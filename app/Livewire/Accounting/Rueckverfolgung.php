<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Support\Chargenverfolgung;
use App\Domains\Accounting\Support\MhdMonitor;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Rueckverfolgung extends Component
{
    public string $charge = '';

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung', 'kueche']));
    }

    public function render(Chargenverfolgung $verfolgung, MhdMonitor $monitor)
    {
        $tenantId = app(CurrentTenant::class)->id();

        $mhdListe = $monitor->ablaufend($tenantId);
        $chargenTreffer = $this->charge !== ''
            ? $verfolgung->verfolge(trim($this->charge), $tenantId)
            : [];

        return view('livewire.accounting.rueckverfolgung', [
            'mhdListe' => $mhdListe,
            'chargenTreffer' => $chargenTreffer,
        ]);
    }
}
