<?php

namespace App\Livewire;

use App\Domains\CarePlanning\Support\SisAreaCatalog;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Actions\ApproveTranscription;
use App\Domains\Speech\Actions\StartTranscription;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Speech extends Component
{
    public ?int $resident_id = null;

    public string $kontext = 'mobilitaet';

    public function startDemo(StartTranscription $start): void
    {
        $this->validate([
            'resident_id' => ['required', 'exists:residents,id'],
            'kontext' => ['required', 'string'],
        ]);

        // Demo: erzeugt eine Platzhalter-Audionotiz. Mit SPEECH_FAKE + sync-Queue
        // durchläuft die Kette sofort bis zum Status „review".
        $audio = UploadedFile::fake()->create('sprachnotiz.webm', 24, 'audio/webm');
        $start->handle($this->resident_id, $this->kontext, $audio);

        session()->flash('status', 'Sprachnotiz aufgenommen und verarbeitet — bereit zur Freigabe.');
    }

    public function approve(int $jobId, ApproveTranscription $approve): void
    {
        $job = TranscriptionJob::where('status', TranscriptionStatus::Review)->findOrFail($jobId);
        $felder = $job->sis_vorschlag['felder'] ?? [];

        $approve->handle($job, auth()->id(), ['felder' => $felder]);

        session()->flash('status', 'Freigegeben — als SIS-Erhebung übernommen, Job abgeschlossen.');
    }

    public function render()
    {
        return view('livewire.speech', [
            'residents' => Resident::orderBy('name')->get(),
            'areas' => SisAreaCatalog::all(),
            'jobs' => TranscriptionJob::with('resident')->latest('id')->take(20)->get(),
        ]);
    }
}
