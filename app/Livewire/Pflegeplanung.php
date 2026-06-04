<?php

namespace App\Livewire;

use App\Domains\CarePlanning\Support\SisAreaCatalog;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Pflegeplanung extends Component
{
    private const AVATAR_BG = ['#C9D8CF', '#D2C6B8', '#C7D2DA', '#D9CBD2', '#CFD8C4'];

    public function mount(): void
    {
        // Tenant-Kontext: im Live-Betrieb aus dem eingeloggten Nutzer, sonst Demo-Mandant.
        $tenant = auth()->user()?->tenant ?? Tenant::query()->first();
        if ($tenant) {
            app(CurrentTenant::class)->set($tenant);
        }
    }

    #[Layout('layouts.sis')]
    public function render()
    {
        return view('livewire.pflegeplanung', [
            'areas' => SisAreaCatalog::all(),
            'residents' => $this->residents(),
            'nurse' => [
                'name' => auth()->user()?->name ?? 'Bettina Mertens',
                'initials' => $this->initials(auth()->user()?->name ?? 'Bettina Mertens'),
                'schicht' => 'Frühdienst',
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function residents(): array
    {
        $residents = Resident::query()
            ->where('status', 'aktiv')
            ->with(['room', 'sisAssessments' => fn ($q) => $q->current()->latest('id')->with('topicFields')])
            ->orderBy('name')
            ->get();

        return $residents->values()->map(function (Resident $r, int $i) {
            $sis = $r->sisAssessments->first();
            $areas = [];

            if ($sis) {
                foreach ($sis->topicFields as $tf) {
                    $sd = $tf->strukturdaten ?? [];
                    $areas[$tf->themenfeld->value] = [
                        'status' => $sd['status'] ?? 'stabil',
                        'ressourcen' => $sd['ressourcen'] ?? [],
                        'belastungen' => $sd['belastungen'] ?? [],
                        'ziele' => $sd['ziele'] ?? [],
                        'massnahmen' => $sd['massnahmen'] ?? [],
                        'updated' => $sd['updated'] ?? '—',
                        'by' => $sd['by'] ?? '',
                    ];
                }
            }

            $akut = null;
            foreach ($areas as $key => $area) {
                if ($area['status'] === 'handlung' && ! empty($area['belastungen'])) {
                    $akut = ['areaKey' => $key, 'text' => $area['belastungen'][0]];
                    break;
                }
            }

            return [
                'id' => $r->id,
                'name' => $r->name,
                'room' => $r->room?->nummer ?? '—',
                'pflegegrad' => $r->pflegegrad,
                'initials' => $this->initials($r->name),
                'avatarBg' => self::AVATAR_BG[$i % count(self::AVATAR_BG)],
                'eingangsfrage' => $sis?->eingangsfrage,
                'areas' => $areas,
                'akut' => $akut,
            ];
        })->all();
    }

    private function initials(string $name): string
    {
        return Str::of($name)->explode(' ')
            ->filter()
            ->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
