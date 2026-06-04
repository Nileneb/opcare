<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\RecordVital;
use App\Domains\Medication\Data\VitalData;
use App\Domains\Medication\Enums\VitalType;
use App\Domains\Medication\Models\VitalReading;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Vitalwerte extends Component
{
    #[Locked]
    public Resident $resident;

    public string $typ = 'puls';

    public ?float $wert = null;

    public ?float $wert2 = null;

    public string $notiz = '';

    public function mount(Resident $resident): void
    {
        abort_unless(auth()->check(), 403);
        $this->resident = $resident;
    }

    private function darfErfassen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft']));
    }

    public function erfassen(RecordVital $record): void
    {
        abort_unless($this->darfErfassen(), 403);
        $data = $this->validate([
            'typ' => ['required', 'in:'.implode(',', array_column(VitalType::cases(), 'value'))],
            'wert' => ['required', 'numeric'],
            'wert2' => ['nullable', 'numeric'],
            'notiz' => ['nullable', 'string'],
        ]);

        $record->handle(new VitalData(
            resident_id: $this->resident->id,
            typ: $data['typ'],
            wert: (float) $data['wert'],
            gemessen_von: auth()->id(),
            wert2: isset($data['wert2']) ? (float) $data['wert2'] : null,
            notiz: trim($this->notiz) ?: null,
        ));

        $this->reset('wert', 'wert2', 'notiz');
        session()->flash('status', 'Vitalwert erfasst.');
    }

    public function render()
    {
        return view('livewire.medication.vitalwerte', [
            'typen' => VitalType::cases(),
            'messungen' => VitalReading::where('resident_id', $this->resident->id)
                ->orderByDesc('gemessen_am')->limit(20)->get(),
        ]);
    }
}
