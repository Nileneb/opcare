<?php

namespace App\Livewire\SocialCare;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\SocialCare\Enums\Handlungsfeld;
use App\Domains\SocialCare\Models\Praeventionsprogramm;
use App\Domains\SocialCare\Models\Praeventionsteilnahme;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Prävention in der stationären Pflege (§ 5 SGB XI, kassenfinanziert): Programme je Handlungsfeld anlegen,
 * Teilnahmen je Bewohner dokumentieren und den Verwendungsnachweis (Teilnahmen + Minuten) ablesen.
 */
#[Layout('layouts.app')]
class Praevention extends Component
{
    public string $p_handlungsfeld = 'bewegung';

    public string $p_titel = '';

    public string $p_frequenz = '';

    public string $p_verantwortlich = '';

    public ?int $teilnProgramm = null;

    public string $t_datum = '';

    public int $t_dauer = 30;

    /** @var array<int, int> */
    public array $t_teilnehmer = [];

    public string $t_beobachtung = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->t_datum = today()->toDateString();
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'betreuungskraft']));
    }

    public function programmAnlegen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'p_handlungsfeld' => ['required', 'in:'.implode(',', array_map(fn ($h) => $h->value, Handlungsfeld::cases()))],
            'p_titel' => ['required', 'string', 'max:160'],
            'p_frequenz' => ['nullable', 'string', 'max:60'],
            'p_verantwortlich' => ['nullable', 'string', 'max:120'],
        ]);
        Praeventionsprogramm::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'handlungsfeld' => $data['p_handlungsfeld'], 'titel' => $data['p_titel'],
            'frequenz' => $data['p_frequenz'] ?: null, 'verantwortlich' => $data['p_verantwortlich'] ?: null,
        ]);
        $this->reset('p_titel', 'p_frequenz', 'p_verantwortlich');
        session()->flash('status', 'Programm angelegt.');
    }

    public function teilnahmeStart(int $programmId): void
    {
        $this->reset('t_teilnehmer', 't_beobachtung');
        $this->teilnProgramm = $programmId;
        $this->t_datum = today()->toDateString();
    }

    public function teilnahmeSpeichern(): void
    {
        abort_unless($this->darf(), 403);
        $programm = Praeventionsprogramm::findOrFail($this->teilnProgramm);
        $this->validate([
            't_datum' => ['required', 'date'],
            't_dauer' => ['required', 'integer', 'min:1', 'max:480'],
            't_teilnehmer' => ['array', 'min:1'],
            't_teilnehmer.*' => ['integer', 'exists:residents,id'],
            't_beobachtung' => ['nullable', 'string', 'max:200'],
        ]);
        foreach (array_unique($this->t_teilnehmer) as $residentId) {
            Praeventionsteilnahme::create([
                'tenant_id' => app(CurrentTenant::class)->id(),
                'praeventionsprogramm_id' => $programm->id, 'resident_id' => (int) $residentId,
                'datum' => $this->t_datum, 'dauer_minuten' => $this->t_dauer, 'beobachtung' => $this->t_beobachtung ?: null,
            ]);
        }
        $this->reset('teilnProgramm', 't_teilnehmer', 't_beobachtung');
        session()->flash('status', 'Teilnahme dokumentiert.');
    }

    public function programmEntfernen(int $id): void
    {
        abort_unless($this->darf(), 403);
        Praeventionsprogramm::findOrFail($id)->delete();
        $this->reset('teilnProgramm');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $programme = Praeventionsprogramm::withCount('teilnahmen')->withSum('teilnahmen', 'dauer_minuten')
            ->where('tenant_id', $tenantId)->orderBy('handlungsfeld')->get()
            ->groupBy(fn (Praeventionsprogramm $p) => $p->handlungsfeld->value);

        return view('livewire.social-care.praevention', [
            'programmeNachFeld' => $programme,
            'handlungsfelder' => Handlungsfeld::cases(),
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
