<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Support\PflegehilfsmittelMonitor;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Pflegehilfsmittel extends Component
{
    public string $monat;

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless(
            $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung', 'pflegefachkraft'])),
            403,
        );

        $this->monat = today()->format('Y-m');
    }

    public function render(PflegehilfsmittelMonitor $monitor)
    {
        $tenantId = app(CurrentTenant::class)->id();

        return view('livewire.accounting.pflegehilfsmittel', [
            'eintraege' => $monitor->verbrauchProBewohner($tenantId, $this->monat),
            'pauschale' => PflegehilfsmittelMonitor::PAUSCHALE,
        ]);
    }
}
