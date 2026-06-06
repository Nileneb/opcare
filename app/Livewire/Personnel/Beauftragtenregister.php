<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Models\Beauftragtenbestellung;
use App\Domains\Personnel\Models\Beauftragtenrolle;
use App\Domains\Personnel\Support\BeauftragtenrolleDefaults;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Beauftragten-Register: Pflicht-/„befähigte-Person"-Rollen je Einrichtung mit benannter Person,
 * Fälligkeits-Ampel und Hinweis auf unbesetzte Pflicht-Rollen (Compliance).
 */
#[Layout('layouts.app')]
class Beauftragtenregister extends Component
{
    public ?int $b_rolle = null;

    public ?int $b_user = null;

    public string $b_datum = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        BeauftragtenrolleDefaults::ensureFor(app(CurrentTenant::class)->id());
        $this->b_datum = today()->toDateString();
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function bestellen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'b_rolle' => ['required', 'integer', 'exists:beauftragten_rollen,id'],
            'b_user' => ['required', 'integer', 'exists:users,id'],
            'b_datum' => ['required', 'date'],
        ]);
        $rolle = Beauftragtenrolle::findOrFail($data['b_rolle']);
        $gueltigBis = $rolle->auffrischung_monate
            ? Carbon::parse($data['b_datum'])->addMonths($rolle->auffrischung_monate)->toDateString()
            : null;

        Beauftragtenbestellung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'beauftragten_rolle_id' => $rolle->id, 'user_id' => $data['b_user'],
            'bestellt_am' => $data['b_datum'], 'gueltig_bis' => $gueltigBis,
        ]);
        $this->reset('b_rolle', 'b_user');
        session()->flash('status', $rolle->name.' bestellt.');
    }

    public function abbestellen(int $id): void
    {
        abort_unless($this->darf(), 403);
        Beauftragtenbestellung::findOrFail($id)->update(['abbestellt_am' => today()->toDateString()]);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $rollen = Beauftragtenrolle::with(['bestellungen' => fn ($q) => $q->whereNull('abbestellt_am')->with('user')])
            ->where('tenant_id', $tenantId)->where('aktiv', true)->orderBy('bereich')->orderBy('id')->get();

        $unbesetztePflicht = $rollen->filter(fn (Beauftragtenrolle $r) => $r->pflicht && $r->bestellungen->isEmpty())->count();
        $ueberfaellig = $rollen->flatMap->bestellungen->filter(fn ($b) => $b->status() === 'ueberfaellig')->count();

        return view('livewire.personnel.beauftragtenregister', [
            'rollen' => $rollen,
            'users' => User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get(),
            'unbesetztePflicht' => $unbesetztePflicht,
            'ueberfaellig' => $ueberfaellig,
        ]);
    }
}
