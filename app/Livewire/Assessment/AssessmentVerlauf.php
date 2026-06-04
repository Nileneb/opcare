<?php

namespace App\Livewire\Assessment;

use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Masterdata\Models\Resident;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class AssessmentVerlauf extends Component
{
    #[Locked]
    public Resident $resident;

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    public function render()
    {
        $aktuelle = Assessment::current()
            ->with('instrument')
            ->where('resident_id', $this->resident->id)
            ->latest('durchgefuehrt_am')
            ->get()
            ->unique('instrument_id');

        $historie = Assessment::with('instrument')
            ->where('resident_id', $this->resident->id)
            ->orderByDesc('durchgefuehrt_am')
            ->limit(50)
            ->get();

        return view('livewire.assessment.assessment-verlauf', [
            'aktuelle' => $aktuelle,
            'historie' => $historie,
            'instrumente' => Instrument::current()->orderBy('name')->get(),
        ]);
    }
}
