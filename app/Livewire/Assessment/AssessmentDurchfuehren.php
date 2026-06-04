<?php

namespace App\Livewire\Assessment;

use App\Domains\Assessment\Actions\ConductAssessment;
use App\Domains\Assessment\Actions\EscalateToQuality;
use App\Domains\Assessment\Actions\SyncRiskItem;
use App\Domains\Assessment\Data\AssessmentInputData;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\Instrument;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class AssessmentDurchfuehren extends Component
{
    use ScopesTenantValidation;

    #[Locked]
    public Resident $resident;

    #[Locked]
    public Instrument $instrument;

    /** instrument_item_id => assessment_option_id */
    public array $answers = [];

    public string $notiz = '';

    public function mount(Resident $resident, Instrument $instrument): void
    {
        $this->authorize('conduct', Assessment::class);
        $this->resident = $resident;
        $this->instrument = $instrument->load('items.options');
    }

    public function speichern(ConductAssessment $conduct, SyncRiskItem $sync, EscalateToQuality $escalate): void
    {
        $this->authorize('conduct', Assessment::class);

        $itemIds = $this->instrument->items->pluck('id')->all();
        $this->validate(
            collect($itemIds)->mapWithKeys(fn ($id) => ["answers.$id" => ['required', $this->tenantExists('assessment_options')]])->all(),
            [],
            collect($itemIds)->mapWithKeys(fn ($id) => ["answers.$id" => 'Antwort'])->all(),
        );

        $assessment = $conduct->handle(new AssessmentInputData(
            resident_id: $this->resident->id,
            instrument_id: $this->instrument->id,
            created_by: auth()->id(),
            answers: array_map('intval', $this->answers),
            notiz: trim($this->notiz) ?: null,
        ));

        $sync->handle($assessment);
        $escalate->handle($assessment);

        session()->flash('status', 'Assessment gespeichert: '.$assessment->risk_band?->label());
        $this->redirectRoute('assessment.verlauf', ['resident' => $this->resident->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.assessment.assessment-durchfuehren');
    }
}
