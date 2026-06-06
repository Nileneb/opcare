<?php

namespace App\Livewire\SocialCare;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\SocialCare\Enums\BetreuungsArt;
use App\Domains\SocialCare\Enums\BetreuungsTyp;
use App\Domains\SocialCare\Models\Betreuungsangebot;
use App\Domains\SocialCare\Services\SocialCareService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Soziale Betreuung (§ 43b SGB XI): Betreuungs-/Aktivierungsangebote planen, Teilnahme je Bewohner
 * dokumentieren und die Betreuungsbilanz je Bewohner (Einheiten/Minuten im Monat) als Nachweis sehen.
 */
#[Layout('layouts.app')]
class Betreuung extends Component
{
    public string $datum = '';

    public string $a_art = 'gedaechtnistraining';

    public string $a_titel = '';

    public string $a_typ = 'gruppe';

    public int $a_dauer = 30;

    public string $a_beginn = '10:00';

    public ?int $teilnAngebot = null;

    /** @var array<int, int> */
    public array $teilnehmer = [];

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->datum = today()->toDateString();
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft']));
    }

    public function tag(int $delta): void
    {
        $this->datum = CarbonImmutable::parse($this->datum)->addDays($delta)->toDateString();
        $this->reset('teilnAngebot', 'teilnehmer');
    }

    public function angebotAnlegen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'a_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, BetreuungsArt::cases()))],
            'a_titel' => ['required', 'string', 'max:160'],
            'a_typ' => ['required', 'in:'.implode(',', array_map(fn ($t) => $t->value, BetreuungsTyp::cases()))],
            'a_dauer' => ['integer', 'min:5', 'max:480'],
            'a_beginn' => ['nullable', 'date_format:H:i'],
        ]);
        Betreuungsangebot::create([
            'datum' => $this->datum, 'beginn' => $data['a_beginn'] ?: null, 'dauer_minuten' => $data['a_dauer'],
            'art' => $data['a_art'], 'typ' => $data['a_typ'], 'titel' => $data['a_titel'], 'leitung_id' => auth()->id(),
        ]);
        $this->reset('a_titel');
        session()->flash('status', 'Angebot angelegt.');
    }

    public function angebotEntfernen(int $id): void
    {
        abort_unless($this->darf(), 403);
        Betreuungsangebot::findOrFail($id)->delete();
        $this->reset('teilnAngebot', 'teilnehmer');
    }

    public function teilnahmeOeffnen(int $angebotId): void
    {
        $angebot = Betreuungsangebot::with('teilnahmen')->findOrFail($angebotId);
        $this->teilnAngebot = $angebotId;
        $this->teilnehmer = $angebot->teilnahmen->where('teilgenommen', true)->pluck('resident_id')->all();
    }

    public function teilnahmeSpeichern(): void
    {
        abort_unless($this->darf(), 403);
        $angebot = Betreuungsangebot::findOrFail($this->teilnAngebot);
        $angebot->teilnahmen()->delete();
        foreach (array_unique($this->teilnehmer) as $residentId) {
            $angebot->teilnahmen()->create(['resident_id' => (int) $residentId]);
        }
        $this->reset('teilnAngebot', 'teilnehmer');
        session()->flash('status', 'Teilnahme dokumentiert.');
    }

    public function render(SocialCareService $service)
    {
        $tenantId = app(CurrentTenant::class)->id();
        $tag = CarbonImmutable::parse($this->datum);
        $angebote = Betreuungsangebot::with('teilnahmen', 'leitung')
            ->whereDate('datum', $this->datum)->orderBy('beginn')->get();

        $bilanz = $service->bilanz($tag->startOfMonth()->toDateString(), $tag->endOfMonth()->toDateString());
        $residents = Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get();

        return view('livewire.social-care.betreuung', [
            'angebote' => $angebote,
            'residents' => $residents,
            'bilanz' => $bilanz,
            'monatLabel' => $tag->isoFormat('MMMM YYYY'),
            'datumLabel' => $tag->isoFormat('dddd, DD.MM.YYYY'),
            'arten' => BetreuungsArt::cases(),
            'typen' => BetreuungsTyp::cases(),
        ]);
    }
}
