<?php

namespace App\Livewire\Catering;

use App\Domains\Catering\Enums\EssenswunschArt;
use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Essenswunsch;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Catering\Models\Menuewahl;
use App\Domains\Catering\Services\CateringService;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Küche/Hauswirtschaft: küchenrelevante Diäten + allgemeine Essenswünsche der Bewohner (jederzeit sichtbar),
 * Speiseplan mit LMIV-Allergenkennzeichnung (mehrere Gerichte je Mahlzeit zur Auswahl) und die Menüwahl je
 * Bewohner (eine Wahl pro Mahlzeit). Je Gericht werden betroffene Bewohner als Allergen-Hinweis ausgewiesen.
 */
#[Layout('layouts.app')]
class Kueche extends Component
{
    public string $datum = '';

    public string $g_mahlzeit = 'mittag';

    public string $g_bezeichnung = '';

    /** @var array<int, string> */
    public array $g_allergene = [];

    public ?int $ew_resident = null;

    public string $ew_art = 'abneigung';

    public string $ew_text = '';

    public ?int $wahlGericht = null;

    /** @var array<int, int> */
    public array $waehler = [];

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
        $this->reset('wahlGericht', 'waehler');
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
        $this->reset('wahlGericht', 'waehler');
    }

    public function essenswunschAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'ew_resident' => ['required', 'integer', 'exists:residents,id'],
            'ew_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, EssenswunschArt::cases()))],
            'ew_text' => ['required', 'string', 'max:160'],
        ]);
        Essenswunsch::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'resident_id' => $data['ew_resident'], 'art' => $data['ew_art'], 'text' => $data['ew_text'],
        ]);
        $this->reset('ew_text');
        session()->flash('status', 'Essenswunsch hinterlegt.');
    }

    public function essenswunschEntfernen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Essenswunsch::findOrFail($id)->delete();
    }

    public function wahlOeffnen(int $gerichtId): void
    {
        $gericht = Gericht::with('menuewahlen')->findOrFail($gerichtId);
        $this->wahlGericht = $gerichtId;
        $this->waehler = $gericht->menuewahlen->pluck('resident_id')->all();
    }

    public function wahlSpeichern(): void
    {
        abort_unless($this->darfSehen(), 403);
        $gericht = Gericht::findOrFail($this->wahlGericht);
        // eine Wahl je Mahlzeit: Geschwister-Gerichte desselben Tages + derselben Mahlzeit.
        $mealGerichte = Gericht::whereDate('datum', $gericht->datum)->where('mahlzeit', $gericht->mahlzeit->value)->pluck('id');
        $gericht->menuewahlen()->delete();
        foreach (array_unique($this->waehler) as $residentId) {
            Menuewahl::whereIn('gericht_id', $mealGerichte)->where('resident_id', (int) $residentId)->delete();
            $gericht->menuewahlen()->create(['resident_id' => (int) $residentId]);
        }
        $this->reset('wahlGericht', 'waehler');
        session()->flash('status', 'Menüwahl gespeichert.');
    }

    public function render(CateringService $service)
    {
        $bewohner = $service->diaetBewohner();
        $alleBewohner = Resident::where('tenant_id', app(CurrentTenant::class)->id())->where('status', 'aktiv')->orderBy('name')->get();
        $gerichte = Gericht::with('menuewahlen.resident')->whereDate('datum', $this->datum)->get()
            ->sortBy(fn (Gericht $g) => $g->mahlzeit->sort())->values();

        $betroffenePro = [];
        foreach ($gerichte as $g) {
            $betroffenePro[$g->id] = $service->betroffene($g, $bewohner);
        }

        return view('livewire.catering.kueche', [
            'service' => $service,
            'bewohner' => $bewohner,
            'alleBewohner' => $alleBewohner,
            'gerichte' => $gerichte,
            'betroffenePro' => $betroffenePro,
            'essenswuensche' => Essenswunsch::with('resident')->orderBy('resident_id')->get(),
            'datumLabel' => CarbonImmutable::parse($this->datum)->isoFormat('dddd, DD.MM.YYYY'),
            'mahlzeiten' => Mahlzeit::cases(),
            'allergene' => LmivAllergen::cases(),
            'essensArten' => EssenswunschArt::cases(),
        ]);
    }
}
