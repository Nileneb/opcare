<?php

namespace App\Livewire\Qdvs;

use App\Domains\Qdvs\Actions\BuildQdvsExport;
use App\Domains\Qdvs\Models\QdvsExport;
use App\Domains\Qdvs\Support\SpecRegistry;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Export extends Component
{
    public string $stichtag = '';

    public string $specKey = 'csv-v1';

    public function mount(): void
    {
        // WHY: QDVS-Export enthält pseudonymisierte Gesundheitsdaten — nur Leitungs-Rollen. mount() blockt auch den Livewire-Update-Pfad.
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );

        $this->stichtag = today()->toDateString();
    }

    public function erstellen(BuildQdvsExport $build): void
    {
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );

        $this->validate([
            'stichtag' => ['required', 'date'],
            'specKey' => ['required', 'string'],
        ]);

        $build->handle($this->stichtag, $this->specKey);

        session()->flash('status', 'Export erstellt.');
    }

    public function render(SpecRegistry $registry): View
    {
        return view('livewire.qdvs.export', [
            'specs' => $registry->all(),
            'exports' => QdvsExport::latest('id')->take(20)->get(),
        ]);
    }
}
