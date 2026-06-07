<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Support\Betriebsanweisung;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BetriebsanweisungDruck extends Component
{
    public Artikel $artikel;

    public function mount(Artikel $artikel): void
    {
        $u = auth()->user();
        abort_unless(
            $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik', 'kueche', 'buchhaltung'])),
            403,
        );

        // WHY(IDOR): Artikel muss dem aktuellen Tenant gehören.
        abort_unless($artikel->tenant_id === app(CurrentTenant::class)->id(), 404);
        abort_unless($artikel->gefahrstoffDaten !== null, 404);

        $this->artikel = $artikel;
    }

    public function render()
    {
        $sektionen = Betriebsanweisung::fuer($this->artikel->gefahrstoffDaten);

        return view('livewire.accounting.betriebsanweisung-druck', compact('sektionen'));
    }
}
