<?php

namespace App\Livewire;

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\CareReport;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public function render()
    {
        return view('livewire.overview', [
            'stats' => [
                'residents' => Resident::where('status', 'aktiv')->count(),
                'sis' => SisAssessment::current()->count(),
                'measures' => CareMeasure::current()->count(),
                'reports' => CareReport::current()->count(),
                'review' => TranscriptionJob::where('status', TranscriptionStatus::Review)->count(),
            ],
            'residents' => Resident::where('status', 'aktiv')->with('room')->orderBy('name')->take(6)->get(),
        ]);
    }
}
