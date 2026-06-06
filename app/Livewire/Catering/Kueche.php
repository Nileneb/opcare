<?php

namespace App\Livewire\Catering;

use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Catering\Services\CateringService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Küche/Hauswirtschaft: sieht die küchenrelevanten Diäten (Lebensmittelallergien + Kostformen) aus den
 * vorhandenen Pflegedaten und pflegt den Speiseplan mit LMIV-Allergenkennzeichnung. Je Gericht werden
 * betroffene Bewohner als Hinweis ausgewiesen (unscharfer Abgleich, keine Garantie).
 */
#[Layout('layouts.app')]
class Kueche extends Component
{
    public string $datum = '';

    public string $g_mahlzeit = 'mittag';

    public string $g_bezeichnung = '';

    /** @var array<int, string> */
    public array $g_allergene = [];

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->datum = today()->toDateString();
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche']));
    }

    public function tag(int $delta): void
    {
        $this->datum = CarbonImmutable::parse($this->datum)->addDays($delta)->toDateString();
    }

    public function gerichtAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'g_mahlzeit' => ['required', 'in:'.implode(',', array_map(fn ($m) => $m->value, Mahlzeit::cases()))],
            'g_bezeichnung' => ['required', 'string', 'max:160'],
            'g_allergene' => ['array'],
            'g_allergene.*' => ['in:'.implode(',', array_map(fn ($a) => $a->value, LmivAllergen::cases()))],
        ]);

        Gericht::create([
            'datum' => $this->datum, 'mahlzeit' => $data['g_mahlzeit'],
            'bezeichnung' => $data['g_bezeichnung'], 'allergene' => array_values($data['g_allergene']),
        ]);
        $this->reset('g_bezeichnung', 'g_allergene');
        session()->flash('status', 'Gericht im Speiseplan ergänzt.');
    }

    public function gerichtEntfernen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Gericht::findOrFail($id)->delete();
    }

    public function render(CateringService $service)
    {
        $bewohner = $service->diaetBewohner();
        $gerichte = Gericht::whereDate('datum', $this->datum)->get()
            ->sortBy(fn (Gericht $g) => $g->mahlzeit->sort())->values();

        $betroffenePro = [];
        foreach ($gerichte as $g) {
            $betroffenePro[$g->id] = $service->betroffene($g, $bewohner);
        }

        return view('livewire.catering.kueche', [
            'service' => $service,
            'bewohner' => $bewohner,
            'gerichte' => $gerichte,
            'betroffenePro' => $betroffenePro,
            'datumLabel' => CarbonImmutable::parse($this->datum)->isoFormat('dddd, DD.MM.YYYY'),
            'mahlzeiten' => Mahlzeit::cases(),
            'allergene' => LmivAllergen::cases(),
        ]);
    }
}
