<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AdministerOnDemand;
use App\Domains\Medication\Actions\DiscontinuePrescription;
use App\Domains\Medication\Models\Prescription;
use App\Domains\Medication\Models\PrescriptionSchedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Verordnungen extends Component
{
    #[Locked]
    public Resident $resident;

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    private function darfVerordnen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function absetzen(int $id, DiscontinuePrescription $discontinue): void
    {
        abort_unless($this->darfVerordnen(), 403);
        $rx = Prescription::where('resident_id', $this->resident->id)->findOrFail($id);
        $discontinue->handle($rx, auth()->id());
        session()->flash('status', 'Verordnung abgesetzt.');
    }

    public function bedarfGeben(int $scheduleId, float $dosis, AdministerOnDemand $onDemand): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']) || auth()->user()?->isSuperAdmin(),
            403,
        );
        $schedule = PrescriptionSchedule::whereHas(
            'prescription',
            fn ($q) => $q->where('resident_id', $this->resident->id),
        )->findOrFail($scheduleId);
        $onDemand->handle($schedule, auth()->id(), $dosis, 'Bedarfsgabe');
        session()->flash('status', 'Bedarfsgabe dokumentiert.');
    }

    public function render()
    {
        $verordnungen = Prescription::with(['medProduct', 'schedules', 'physician', 'situation'])
            ->where('resident_id', $this->resident->id)
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.medication.verordnungen', [
            'aktive' => $verordnungen->filter(fn ($r) => $r->ist_aktiv),
            'beendete' => $verordnungen->filter(fn ($r) => ! $r->ist_aktiv),
        ]);
    }
}
