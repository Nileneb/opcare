<?php

namespace App\Livewire\Medication;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AdministerMedication;
use App\Domains\Medication\Actions\RefuseMedication;
use App\Domains\Medication\Data\AdministerData;
use App\Domains\Medication\Enums\AdministrationStatus;
use App\Domains\Medication\Models\MedicationAdministration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Stellplan extends Component
{
    #[Locked]
    public Resident $resident;

    public string $tag;

    public function mount(Resident $resident): void
    {
        $this->resident = $resident;
        $this->tag = today()->toDateString();
    }

    public function quittieren(int $id, AdministerMedication $administer): void
    {
        $a = MedicationAdministration::where('resident_id', $this->resident->id)->findOrFail($id);
        $this->authorize('administer', $a);
        $productId = $a->schedule?->prescription?->med_product_id;
        $administer->handle($a, new AdministerData(quittiert_von: auth()->id(), med_product_id: $productId));
        session()->flash('status', 'Gabe quittiert.');
    }

    public function ablehnen(int $id, RefuseMedication $refuse): void
    {
        $a = MedicationAdministration::where('resident_id', $this->resident->id)->findOrFail($id);
        $this->authorize('administer', $a);
        $refuse->handle($a, auth()->id(), 'Abgelehnt am Bett');
        session()->flash('status', 'Als abgelehnt vermerkt.');
    }

    public function render()
    {
        $gaben = MedicationAdministration::where('resident_id', $this->resident->id)
            ->whereDate('soll_zeitpunkt', $this->tag)
            ->with('schedule.prescription.medProduct')
            ->orderBy('soll_zeitpunkt')
            ->get();

        return view('livewire.medication.stellplan', [
            'gaben' => $gaben,
            'offen' => AdministrationStatus::Geplant,
        ]);
    }
}
