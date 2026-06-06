<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\AbwesenheitTyp;
use App\Domains\Scheduling\Models\Abwesenheit;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\ShiftSwapRequest;
use App\Domains\Scheduling\Support\ShiftCoverageService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Tauschbörse + Krankmeldung: Mitarbeitende melden sich krank (öffnet ihre Dienste als Vertretung), bieten
 * Dienste zum Tausch an und übernehmen offene Dienste (nach harter ArbZG-Prüfung). PDL kann für andere melden.
 */
#[Layout('layouts.app')]
class Tauschboerse extends Component
{
    public ?int $km_user = null;

    public string $km_typ = 'krank';

    public string $km_von = '';

    public string $km_bis = '';

    public string $km_notiz = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->km_user = auth()->id();
        $this->km_von = today()->toDateString();
        $this->km_bis = today()->toDateString();
    }

    private function darfFuerAndere(): bool
    {
        return auth()->user()?->can('manage', Shift::class) ?? false;
    }

    public function krankmelden(ShiftCoverageService $service): void
    {
        $data = $this->validate([
            'km_user' => ['required', 'integer', 'exists:users,id'],
            'km_typ' => ['required', 'in:'.implode(',', array_map(fn ($t) => $t->value, AbwesenheitTyp::cases()))],
            'km_von' => ['required', 'date'],
            'km_bis' => ['required', 'date', 'after_or_equal:km_von'],
            'km_notiz' => ['nullable', 'string', 'max:200'],
        ]);
        // nur sich selbst, außer mit Planungsrecht
        $userId = ($this->km_user === auth()->id() || $this->darfFuerAndere()) ? (int) $data['km_user'] : auth()->id();
        $user = User::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($userId);

        $service->krankmelden($user, AbwesenheitTyp::from($data['km_typ']), $data['km_von'], $data['km_bis'], $this->km_notiz ?: null, auth()->id());
        $this->reset('km_notiz');
        session()->flash('status', 'Abwesenheit erfasst — betroffene Dienste sind als Vertretung offen.');
    }

    public function tauschAnbieten(int $assignmentId, ShiftCoverageService $service): void
    {
        $assignment = ShiftAssignment::where('user_id', auth()->id())->findOrFail($assignmentId);
        $service->tauschAnbieten($assignment, null);
        session()->flash('status', 'Dienst zum Tausch angeboten.');
    }

    public function uebernehmen(int $requestId, ShiftCoverageService $service): void
    {
        $request = ShiftSwapRequest::with('assignment')->findOrFail($requestId);
        try {
            $service->uebernehmen($request, auth()->user());
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        session()->flash('status', 'Dienst übernommen.');
    }

    public function zurueckziehen(int $requestId, ShiftCoverageService $service): void
    {
        $request = ShiftSwapRequest::where('requested_by', auth()->id())->findOrFail($requestId);
        $service->zurueckziehen($request);
        session()->flash('status', 'Anfrage zurückgezogen.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $uid = auth()->id();
        $heute = today()->toDateString();

        $meineDienste = ShiftAssignment::with('shift')->where('user_id', $uid)->where('dienst_am', '>=', $heute)
            ->orderBy('dienst_am')->get();
        $offeneIds = ShiftSwapRequest::where('status', 'offen')->pluck('shift_assignment_id')->all();

        $offene = ShiftSwapRequest::with(['assignment.shift', 'anfrager.employeeProfile'])->where('tenant_id', $tenantId)->where('status', 'offen')
            ->get()->filter(fn ($r) => $r->assignment && $r->assignment->dienst_am->toDateString() >= $heute);

        $offeneFremd = $offene->filter(fn ($r) => $r->requested_by !== $uid)->values();
        // Eignung der aktuellen Person je offener Anfrage (null = darf übernehmen, sonst Grund).
        $hindernisse = [];
        $me = auth()->user();
        $coverage = app(ShiftCoverageService::class);
        foreach ($offeneFremd as $r) {
            $hindernisse[$r->id] = $coverage->uebernahmeHindernis($r, $me);
        }

        return view('livewire.scheduling.tauschboerse', [
            'meineDienste' => $meineDienste,
            'offeneIds' => $offeneIds,
            'offeneFremd' => $offeneFremd,
            'hindernisse' => $hindernisse,
            'meineAnfragen' => $offene->filter(fn ($r) => $r->requested_by === $uid)->values(),
            'meineAbwesenheiten' => Abwesenheit::where('user_id', $uid)->where('bis', '>=', $heute)->orderBy('von')->get(),
            'abwesenheitsTypen' => AbwesenheitTyp::cases(),
            'darfFuerAndere' => $this->darfFuerAndere(),
            'kollegen' => $this->darfFuerAndere() ? User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get() : collect(),
        ]);
    }
}
