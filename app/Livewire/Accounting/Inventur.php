<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Actions\InventurAbschliessen;
use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Inventur as InventurModel;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Accounting\Support\Lagerwert;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Inventur-Eintrittspunkt (§§ 240/241 HGB): Kampagne starten, Zählliste je Position erfassen, abschließen
 * (bucht Differenzen, friert den Bestandswert ein). Nur Buchhaltung/Admin. Lookups laufen tenant-gescopt.
 */
#[Layout('layouts.app')]
class Inventur extends Component
{
    public ?string $neu_stichtag = null;

    public ?string $neu_abteilung = null;

    /** @var array<int, float|string|null> */
    public array $ist = [];

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->neu_stichtag = now()->toDateString();
    }

    private function darf(): bool
    {
        return (bool) (auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function starten(InventurStarten $action): void
    {
        abort_unless($this->darf(), 403);
        $this->validate([
            'neu_stichtag' => ['required', 'date'],
            'neu_abteilung' => ['nullable'],
        ]);
        $abteilung = $this->neu_abteilung ? Abteilung::tryFrom($this->neu_abteilung) : null;
        $action->handle($this->neu_stichtag, $abteilung, auth()->id());
        session()->flash('status', 'Inventur gestartet.');
    }

    public function zaehlen(int $positionId): void
    {
        abort_unless($this->darf(), 403);
        $menge = $this->ist[$positionId] ?? null;
        if ($menge === null || $menge === '') {
            return;
        }
        $pos = Inventurposition::findOrFail($positionId);
        abort_unless($pos->inventur->offen(), 403);
        $pos->update(['ist_menge' => (float) $menge, 'gezaehlt_von' => auth()->id(), 'gezaehlt_am' => now()]);
    }

    public function abschliessen(int $inventurId, InventurAbschliessen $action): void
    {
        abort_unless($this->darf(), 403);
        $inventur = InventurModel::findOrFail($inventurId);
        $report = $action->handle($inventur, auth()->id());
        session()->flash('status', "Inventur abgeschlossen: {$report['gebucht']} Differenz(en) gebucht, {$report['nicht_gezaehlt']} nicht gezählt.");
    }

    public function render(Lagerwert $lagerwert)
    {
        return view('livewire.accounting.inventur', [
            'offene' => InventurModel::where('status', 'offen')->with('positionen.artikel')->latest()->get(),
            'abgeschlossene' => InventurModel::where('status', 'abgeschlossen')->with('positionen')->latest()->limit(10)->get(),
            'abteilungen' => Abteilung::cases(),
            'bestandswert' => $lagerwert->bestandswertGesamt(app(CurrentTenant::class)->id()),
        ]);
    }
}
