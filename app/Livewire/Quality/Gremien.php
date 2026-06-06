<?php

namespace App\Livewire\Quality;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\GremiumFunktion;
use App\Domains\Quality\Enums\GremiumTyp;
use App\Domains\Quality\Enums\MitgliedArt;
use App\Domains\Quality\Models\Gremium;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gremien & Mitwirkung: Heimbeirat/Bewohnervertretung (HeimmwV, § 10 WBVG), Angehörigenbeirat,
 * Qualitätszirkel (§ 113 SGB XI), Arbeitsschutzausschuss (§ 11 ASiG). Anlegen mit Wahlperiode +
 * Sitzungstakt (Neuwahl-/Sitzungs-Ampel), Mitglieder pflegen, Sitzungen protokollieren.
 */
#[Layout('layouts.app')]
class Gremien extends Component
{
    public ?int $selected = null;

    // neues Gremium
    public string $g_typ = 'heimbeirat';

    public string $g_name = '';

    public string $g_beschreibung = '';

    public ?string $g_gewaehlt_am = null;

    public ?int $g_periode = 24;

    public ?int $g_sitzung_intervall = 3;

    // Mitglied
    public string $m_name = '';

    public string $m_art = 'bewohner';

    public string $m_funktion = 'mitglied';

    public ?int $m_user = null;

    public ?int $m_resident = null;

    // Sitzung
    public ?string $s_datum = null;

    public string $s_thema = '';

    public string $s_protokoll = '';

    public string $s_beschluesse = '';

    public ?int $s_teilnehmer = null;

    public function mount(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->s_datum = today()->toDateString();
    }

    private function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function select(int $id): void
    {
        $this->selected = $id;
    }

    public function updatedGTyp(string $value): void
    {
        $typ = GremiumTyp::tryFrom($value);
        if ($typ !== null) {
            $this->g_periode = $typ->standardPeriodeMonate();
            $this->g_sitzung_intervall = $typ->standardSitzungIntervallMonate();
        }
    }

    public function anlegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'g_typ' => ['required', 'in:'.$this->werte(GremiumTyp::cases())],
            'g_name' => ['required', 'string', 'max:160'],
            'g_beschreibung' => ['nullable', 'string', 'max:500'],
            'g_gewaehlt_am' => ['nullable', 'date'],
            'g_periode' => ['nullable', 'integer', 'min:1', 'max:120'],
            'g_sitzung_intervall' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $g = Gremium::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'typ' => $data['g_typ'], 'name' => $data['g_name'], 'beschreibung' => $data['g_beschreibung'] ?: null,
            'gewaehlt_am' => $data['g_gewaehlt_am'] ?: null,
            'periode_monate' => $data['g_periode'], 'sitzung_intervall_monate' => $data['g_sitzung_intervall'],
        ]);
        $this->reset('g_name', 'g_beschreibung', 'g_gewaehlt_am');
        $this->selected = $g->id;
        session()->flash('status', 'Gremium angelegt.');
    }

    public function mitgliedHinzufuegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $g = $this->current();
        $data = $this->validate([
            'm_name' => ['required', 'string', 'max:120'],
            'm_art' => ['required', 'in:'.$this->werte(MitgliedArt::cases())],
            'm_funktion' => ['required', 'in:'.$this->werte(GremiumFunktion::cases())],
            'm_user' => ['nullable', 'integer', 'exists:users,id'],
            'm_resident' => ['nullable', 'integer', 'exists:residents,id'],
        ]);
        $g->mitglieder()->create([
            'tenant_id' => $g->tenant_id, 'name' => $data['m_name'], 'art' => $data['m_art'],
            'funktion' => $data['m_funktion'], 'user_id' => $data['m_user'] ?: null, 'resident_id' => $data['m_resident'] ?: null,
        ]);
        $this->reset('m_name', 'm_user', 'm_resident');
        session()->flash('status', 'Mitglied hinzugefügt.');
    }

    public function mitgliedEntfernen(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->current()->mitglieder()->whereKey($id)->delete();
        session()->flash('status', 'Mitglied entfernt.');
    }

    public function sitzungProtokollieren(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $g = $this->current();
        $data = $this->validate([
            's_datum' => ['required', 'date'],
            's_thema' => ['required', 'string', 'max:200'],
            's_protokoll' => ['nullable', 'string', 'max:5000'],
            's_beschluesse' => ['nullable', 'string', 'max:2000'],
            's_teilnehmer' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $g->sitzungen()->create([
            'tenant_id' => $g->tenant_id, 'datum' => $data['s_datum'], 'thema' => $data['s_thema'],
            'protokoll' => $data['s_protokoll'] ?: null, 'beschluesse' => $data['s_beschluesse'] ?: null,
            'teilnehmerzahl' => $data['s_teilnehmer'], 'protokoll_von' => auth()->id(),
        ]);
        $this->reset('s_thema', 's_protokoll', 's_beschluesse', 's_teilnehmer');
        session()->flash('status', 'Sitzung protokolliert.');
    }

    public function neuGewaehlt(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->current()->update(['gewaehlt_am' => today()->toDateString()]);
        session()->flash('status', 'Neuwahl/Konstituierung dokumentiert.');
    }

    public function aufloesen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->current()->update(['aufgeloest_am' => today()->toDateString()]);
        session()->flash('status', 'Gremium aufgelöst.');
    }

    private function current(): Gremium
    {
        return Gremium::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($this->selected);
    }

    /** @param array<int, \BackedEnum> $cases */
    private function werte(array $cases): string
    {
        return implode(',', array_map(fn ($c) => $c->value, $cases));
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $gremien = Gremium::withCount(['mitglieder', 'sitzungen'])->where('tenant_id', $tenantId)->orderBy('typ')->orderBy('name')->get();
        $g = $this->selected ? $gremien->firstWhere('id', $this->selected) : null;
        $handlungsbedarf = $gremien->filter(fn (Gremium $x) => in_array($x->ampel(), ['red', 'amber'], true))->count();

        return view('livewire.quality.gremien', [
            'gremien' => $gremien,
            'gremium' => $g,
            'mitglieder' => $g ? $g->mitglieder()->get() : collect(),
            'sitzungen' => $g ? $g->sitzungen()->with('protokollant')->get() : collect(),
            'handlungsbedarf' => $handlungsbedarf,
            'typen' => GremiumTyp::cases(),
            'arten' => MitgliedArt::cases(),
            'funktionen' => GremiumFunktion::cases(),
            'mitarbeiter' => User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get(),
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
