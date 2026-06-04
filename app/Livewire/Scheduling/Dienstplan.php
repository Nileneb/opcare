<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dienstplan extends Component
{
    public ?int $userId = null;

    public ?int $shiftId = null;

    public string $dienstAm = '';

    public function mount(): void
    {
        // WHY: Nav-Verstecken ist keine Zugriffskontrolle — Guard in mount UND Action.
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->dienstAm = today()->toDateString();
    }

    public function zuweisen(AssignShift $assign): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $data = $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'shiftId' => ['required', 'exists:shifts,id'],
            'dienstAm' => ['required', 'date'],
        ]);

        $assign->handle(new ShiftAssignmentData(
            user_id: $data['userId'], shift_id: $data['shiftId'], dienst_am: $data['dienstAm'],
        ));
        session()->flash('status', 'Dienst eingetragen.');
    }

    public function entfernen(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        ShiftAssignment::findOrFail($id)->delete();
        session()->flash('status', 'Dienst entfernt.');
    }

    public function render()
    {
        return view('livewire.scheduling.dienstplan', [
            'users' => User::where('tenant_id', app(CurrentTenant::class)->id())->orderBy('name')->get(),
            'shifts' => Shift::where('aktiv', true)->orderBy('beginn')->get(),
            'eintraege' => ShiftAssignment::with(['user', 'shift'])
                ->whereDate('dienst_am', $this->dienstAm)->orderBy('shift_id')->get(),
        ]);
    }
}
